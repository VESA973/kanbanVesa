<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;

final class LoginTest extends FunctionalTestCase
{
    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testUserLogsInAndOut(): void
    {
        $client = self::createClient();
        UserFactory::new()->verified()->create(['email' => 'camille@example.com', 'firstName' => 'Camille']);
        $client->request('GET', '/login');

        $client->submitForm('Se connecter', [
            'email' => 'camille@example.com',
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Bonjour Camille');
        self::assertSelectorNotExists('[role=status] form');

        $client->submitForm('Se déconnecter');
        $client->followRedirect();
        self::assertRouteSame('app_login');
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'camille@example.com']);
        $client->request('GET', '/login');

        $client->submitForm('Se connecter', ['email' => 'camille@example.com', 'password' => 'wrong-password']);
        $client->followRedirect();

        self::assertSelectorTextContains('[role=alert]', 'Identifiants invalides.');
    }

    public function testLoginIsThrottledAfterFiveFailures(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'camille@example.com']);

        for ($attempt = 0; $attempt < 6; ++$attempt) {
            $client->request('GET', '/login');
            $client->submitForm('Se connecter', ['email' => 'camille@example.com', 'password' => 'wrong-password']);
        }
        $client->followRedirect();

        self::assertSelectorTextContains('[role=alert]', 'Trop de tentatives de connexion');
    }
}
