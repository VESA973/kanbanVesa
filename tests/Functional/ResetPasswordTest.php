<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\UserFactory;

final class ResetPasswordTest extends FunctionalTestCase
{
    public function testUserResetsPasswordThroughTheEmailedLink(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'camille@example.com']);
        $client->request('GET', '/reset-password');

        $client->submitForm('Envoyer le lien', ['reset_password_request_form[email]' => 'camille@example.com']);
        self::assertResponseRedirects('/reset-password/check-email');
        self::assertQueuedEmailCount(1);

        $client->request('GET', self::extractLink(self::getMailerMessage()));
        self::assertResponseRedirects('/reset-password/reset');
        $client->followRedirect();

        $client->submitForm('Enregistrer le mot de passe', [
            'change_password_form[plainPassword][first]' => 'nouveau-secret',
            'change_password_form[plainPassword][second]' => 'nouveau-secret',
        ]);
        self::assertResponseRedirects('/login');

        $client->request('GET', '/login');
        $client->submitForm('Se connecter', ['email' => 'camille@example.com', 'password' => 'nouveau-secret']);
        self::assertResponseRedirects('/');
    }

    public function testUnknownEmailGetsTheSameAnswerWithoutSendingAnything(): void
    {
        $client = self::createClient();
        $client->request('GET', '/reset-password');

        $client->submitForm('Envoyer le lien', ['reset_password_request_form[email]' => 'nobody@example.com']);

        self::assertResponseRedirects('/reset-password/check-email');
        self::assertQueuedEmailCount(0);
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Vérifiez vos e-mails');
    }

    public function testInvalidTokenIsRejected(): void
    {
        $client = self::createClient();
        $client->request('GET', '/reset-password/reset/invalid-token');
        $client->followRedirect();

        self::assertResponseRedirects('/reset-password');
    }
}
