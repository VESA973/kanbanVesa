<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use App\Repository\UserRepository;
use Symfony\Component\Mime\Email;

final class RegistrationTest extends FunctionalTestCase
{
    public function testUserRegistersIsLoggedInAndReceivesAVerificationEmail(): void
    {
        $client = self::createClient();
        $client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $client->submitForm('Créer mon compte', [
            'registration_form[firstName]' => 'Camille',
            'registration_form[lastName]' => 'Martin',
            'registration_form[email]' => 'Camille@Example.com',
            'registration_form[plainPassword][first]' => 'motdepasse',
            'registration_form[plainPassword][second]' => 'motdepasse',
        ]);

        self::assertResponseRedirects('/');
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'camille@example.com');

        $client->followRedirect();
        $client->followRedirect();
        self::assertSelectorTextContains('nav', 'Camille');
        self::assertSelectorTextContains('[role=status]', "n'est pas encore confirmée");

        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('camille@example.com');
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());
    }

    public function testRegistrationIsRejectedWhenEmailIsAlreadyUsed(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'taken@example.com']);
        $client->request('GET', '/register');

        $client->submitForm('Créer mon compte', [
            'registration_form[firstName]' => 'Camille',
            'registration_form[lastName]' => 'Martin',
            'registration_form[email]' => 'taken@example.com',
            'registration_form[plainPassword][first]' => 'motdepasse',
            'registration_form[plainPassword][second]' => 'motdepasse',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Un compte existe déjà avec cette adresse e-mail.');
        self::assertQueuedEmailCount(0);
    }

    public function testEmptyFormShowsValidationErrors(): void
    {
        $client = self::createClient();
        $client->request('GET', '/register');

        $client->submitForm('Créer mon compte');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[aria-invalid=true]');
    }

    public function testVerificationLinkMarksTheUserAsVerified(): void
    {
        $client = self::createClient();
        $client->request('GET', '/register');
        $client->submitForm('Créer mon compte', [
            'registration_form[firstName]' => 'Camille',
            'registration_form[lastName]' => 'Martin',
            'registration_form[email]' => 'camille@example.com',
            'registration_form[plainPassword][first]' => 'motdepasse',
            'registration_form[plainPassword][second]' => 'motdepasse',
        ]);

        $client->request('GET', self::extractLink(self::getMailerMessage()));

        self::assertResponseRedirects('/');
        $user = UserFactory::repository()->findOneBy(['email' => 'camille@example.com']);
        self::assertNotNull($user);
        self::assertTrue($user->isVerified());
    }

    public function testTamperedVerificationLinkIsRejected(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();

        $client->request('GET', '/verify/email?id='.$user->getId().'&signature=forged&expires=9999999999&token=forged');

        self::assertResponseRedirects('/');
        self::assertFalse($user->isVerified());
    }
}
