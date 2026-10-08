<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mailer;

use App\Entity\EmailLog;
use App\Entity\SmtpSettings;
use App\Enum\EmailStatus;
use App\Enum\SmtpEncryption;
use App\Mailer\EmailJournal;
use App\Mailer\SettingsTransport;
use App\Mailer\SmtpDsnFactory;
use App\Repository\EmailLogRepository;
use App\Repository\SmtpSettingsRepository;
use App\Service\SecretBox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class EmailJournalTest extends TestCase
{
    /** @var list<EmailLog> */
    private array $logs = [];

    public function testRecordsTheMessageIdOfASentEmail(): void
    {
        $email = $this->email();
        $sent = new SentMessage($email, new Envelope(new Address('noreply@example.com'), [new Address('alex@example.com')]));

        $this->journal()->sent($sent);

        self::assertCount(1, $this->logs);
        self::assertSame(EmailStatus::SENT, $this->logs[0]->getStatus());
        self::assertSame('alex@example.com', $this->logs[0]->getRecipients());
        self::assertSame('Invitation', $this->logs[0]->getSubject());
        self::assertSame($sent->getMessageId(), $this->logs[0]->getMessageId());
    }

    public function testAnEmailDroppedForLackOfSmtpServerIsRecorded(): void
    {
        $transport = new SettingsTransport($this->settingsRepository(null), new SmtpDsnFactory(new SecretBox('x')), new NullLogger(), $this->journal());

        self::assertNull($transport->send($this->email()));
        self::assertSame(EmailStatus::NOT_CONFIGURED, $this->logs[0]->getStatus());
        self::assertSame('alex@example.com', $this->logs[0]->getRecipients());
    }

    public function testAFailedDeliveryIsRecordedWithTheServerErrorThenRethrown(): void
    {
        $settings = new SmtpSettings();
        // Nothing listens on port 1: the connection is refused immediately.
        $settings->update('127.0.0.1', 1, SmtpEncryption::NONE, null, 'noreply@example.com', 'TaskBoard');
        $transport = new SettingsTransport($this->settingsRepository($settings), new SmtpDsnFactory(new SecretBox('x')), new NullLogger(), $this->journal());

        try {
            $transport->send($this->email());
            self::fail('The delivery should have failed.');
        } catch (TransportExceptionInterface) {
        }

        self::assertSame(EmailStatus::FAILED, $this->logs[0]->getStatus());
        self::assertNotEmpty($this->logs[0]->getError());
        self::assertNull($this->logs[0]->getMessageId());
    }

    private function email(): Email
    {
        return new Email()->from('noreply@example.com')->to('alex@example.com')->subject('Invitation')->text('Bonjour');
    }

    private function journal(): EmailJournal
    {
        $repository = $this->createMock(EmailLogRepository::class);
        $repository->expects($this->once())->method('record')->willReturnCallback(function (EmailLog $log): void {
            $this->logs[] = $log;
        });

        return new EmailJournal($repository, new MockClock(), new NullLogger());
    }

    private function settingsRepository(?SmtpSettings $settings): SmtpSettingsRepository
    {
        $repository = $this->createStub(SmtpSettingsRepository::class);
        $repository->method('findCurrent')->willReturn($settings);

        return $repository;
    }
}
