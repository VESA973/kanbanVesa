<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\SmtpSettings;
use App\Enum\SmtpEncryption;
use App\Mailer\SettingsTransport;
use App\Repository\SmtpSettingsRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Regression: a queued TemplatedEmail reaches the worker unrendered (Symfony 7 renders it
 * in the transport). Our transport must therefore dispatch the mailer events, otherwise
 * every templated e-mail failed with "A message must have a text or an HTML part".
 */
final class QueuedTemplatedEmailTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testTheTransportRendersTheTemplateBeforeSending(): void
    {
        self::bootKernel();
        $settings = new SmtpSettings();
        // Nothing listens on port 1: rendering happens, then the connection is refused.
        $settings->update('127.0.0.1', 1, SmtpEncryption::NONE, null, 'noreply@example.com', 'Tableau de bord');
        self::getContainer()->get(SmtpSettingsRepository::class)->save($settings);

        $rendered = null;
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(MessageEvent::class, static function (MessageEvent $event) use (&$rendered): void {
            $message = $event->getMessage();
            $rendered = $message instanceof Email ? $message->getHtmlBody() : null;
        }, -1000);

        $email = new TemplatedEmail()
            ->to('alex@example.com')
            ->subject('Invitation')
            ->htmlTemplate('email/invitation.html.twig')
            ->context(['inviterName' => 'Olivia', 'projectName' => 'Mairie 2027', 'isProgram' => true, 'roleKey' => 'project.role.editor', 'token' => str_repeat('a', 64), 'expiresAt' => new \DateTimeImmutable('+7 days')]);

        try {
            self::getContainer()->get(SettingsTransport::class)->send($email);
            self::fail('Nothing listens on port 1.');
        } catch (TransportExceptionInterface) {
        }

        self::assertIsString($rendered, 'The HTML template was rendered by the transport.');
        self::assertStringContainsString('/invitations/'.str_repeat('a', 64), $rendered);
    }
}
