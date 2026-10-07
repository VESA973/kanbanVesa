<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mailer;

use App\Entity\SmtpSettings;
use App\Enum\SmtpEncryption;
use App\Mailer\SettingsTransport;
use App\Mailer\SmtpDsnFactory;
use App\Service\SecretBox;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class SmtpSettingsTest extends TestCase
{
    public function testSecretBoxRoundTripAndKeyDependency(): void
    {
        $box = new SecretBox('app-secret');
        $encrypted = $box->encrypt('p@ss:word/é');

        self::assertNotSame('p@ss:word/é', $encrypted);
        self::assertNotSame($encrypted, $box->encrypt('p@ss:word/é'), 'A random nonce is used each time.');
        self::assertSame('p@ss:word/é', $box->decrypt($encrypted));

        $this->expectException(\RuntimeException::class);
        new SecretBox('another-secret')->decrypt($encrypted);
    }

    /**
     * @return iterable<string, array{SmtpEncryption, int, string}>
     */
    public static function encryptions(): iterable
    {
        yield 'starttls' => [SmtpEncryption::STARTTLS, 587, 'smtp://john%40example.com:p%40ss%3Aword%2F%C3%A9@smtp.example.com:587'];
        yield 'ssl' => [SmtpEncryption::SSL, 465, 'smtps://john%40example.com:p%40ss%3Aword%2F%C3%A9@smtp.example.com:465'];
        yield 'none' => [SmtpEncryption::NONE, 25, 'smtp://john%40example.com:p%40ss%3Aword%2F%C3%A9@smtp.example.com:25?auto_tls=false'];
    }

    #[DataProvider('encryptions')]
    public function testDsnEncodesCredentials(SmtpEncryption $encryption, int $port, string $expected): void
    {
        $box = new SecretBox('app-secret');
        $settings = new SmtpSettings();
        $settings->update('smtp.example.com', $port, $encryption, 'john@example.com', 'noreply@example.com', null);
        $settings->setEncryptedPassword($box->encrypt('p@ss:word/é'));

        self::assertSame($expected, new SmtpDsnFactory($box)->create($settings));
    }

    public function testDsnWithoutCredentials(): void
    {
        $settings = new SmtpSettings();
        $settings->update('localhost', 25, SmtpEncryption::NONE, '', 'noreply@example.com', null);

        self::assertSame('smtp://localhost:25?auto_tls=false', new SmtpDsnFactory(new SecretBox('x'))->create($settings));
    }

    public function testConfiguredSenderReplacesTheDefaultOne(): void
    {
        $settings = new SmtpSettings();
        $settings->update('smtp.example.com', 587, SmtpEncryption::STARTTLS, null, 'Equipe@Example.com', 'Équipe TaskBoard');
        $email = new Email()->from('default@kanban.lan')->to('alex@example.com')->text('Bonjour');

        [$message, $envelope] = SettingsTransport::withSender($settings, $email, null);

        self::assertInstanceOf(Email::class, $message);
        self::assertEquals([new Address('equipe@example.com', 'Équipe TaskBoard')], $message->getFrom());
        self::assertSame('equipe@example.com', $envelope?->getSender()->getAddress());
        self::assertSame('alex@example.com', $envelope->getRecipients()[0]->getAddress());
    }
}
