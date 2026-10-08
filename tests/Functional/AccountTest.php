<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class AccountTest extends FunctionalTestCase
{
    public function testAUserRenamesThemselves(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['firstName' => 'Bernard', 'lastName' => 'Dupont']);
        $client->loginUser($user);

        $client->request('GET', '/account');
        self::assertSelectorTextContains('main', 'Utilisateur');
        $client->submitForm('Enregistrer', ['account_profile_form[firstName]' => 'Bernard', 'account_profile_form[lastName]' => 'Martin']);

        self::assertResponseRedirects('/account');
        self::assertSame('Bernard Martin', refresh($user)->getFullName());
    }

    public function testTheCurrentPasswordIsRequiredToChangeIt(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'bernard@example.com']);
        $client->loginUser($user);

        $client->request('GET', '/account');
        $client->submitForm('Changer le mot de passe', [
            'account_password_form[currentPassword]' => 'pas-le-bon',
            'account_password_form[plainPassword][first]' => 'nouveau-secret',
            'account_password_form[plainPassword][second]' => 'nouveau-secret',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Le mot de passe actuel est incorrect.');
    }

    public function testAChangedPasswordKeepsTheUserSignedInAndWorksAtTheNextLogin(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'bernard@example.com']);
        $client->loginUser($user);

        $client->request('GET', '/account');
        $client->submitForm('Changer le mot de passe', [
            'account_password_form[currentPassword]' => UserFactory::DEFAULT_PASSWORD,
            'account_password_form[plainPassword][first]' => 'nouveau-secret',
            'account_password_form[plainPassword][second]' => 'nouveau-secret',
        ]);
        self::assertResponseRedirects('/account');
        $client->followRedirect();
        self::assertResponseIsSuccessful('Still signed in after the change.');
        self::assertSelectorTextContains('main', 'Votre mot de passe a été changé.');

        $client->request('GET', '/logout');
        $client->getCookieJar()->clear();
        $client->request('GET', '/login');
        $client->submitForm('Se connecter', ['email' => 'bernard@example.com', 'password' => 'nouveau-secret']);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorNotExists('form[action="/login"] [name="password"]');
    }

    public function testANewAccountIsAPlainUserUntilAnAdministratorPromotesIt(): void
    {
        $client = self::createClient();
        $admin = UserFactory::createOne();
        $admin->promoteToAdmin();
        save($admin);
        $bernard = UserFactory::createOne(['firstName' => 'Bernard']);
        self::assertFalse($bernard->isAdmin());

        $client->loginUser($bernard);
        $client->request('GET', '/admin/users');
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/users');
        $client->submit($crawler->filter('form[action="/admin/users/'.$bernard->getId().'/toggle-admin"]')->form());
        self::assertTrue(refresh($bernard)->isAdmin());
    }
}
