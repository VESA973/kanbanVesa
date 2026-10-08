<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Entity\EmailLog;
use App\Enum\EmailStatus;
use App\Repository\EmailLogRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Keeps a trace of every delivery attempt (recipient, status, Message-ID, error).
 */
final readonly class EmailJournal
{
    public function __construct(
        private EmailLogRepository $repository,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function sent(SentMessage $sent): void
    {
        $this->record($sent->getOriginalMessage(), $sent->getEnvelope(), EmailStatus::SENT, $sent->getMessageId());
    }

    public function failed(RawMessage $message, ?Envelope $envelope, \Throwable $error): void
    {
        $this->record($message, $envelope, EmailStatus::FAILED, null, $error->getMessage());
    }

    public function notConfigured(RawMessage $message, ?Envelope $envelope): void
    {
        $this->record($message, $envelope, EmailStatus::NOT_CONFIGURED);
    }

    private function record(RawMessage $message, ?Envelope $envelope, EmailStatus $status, ?string $messageId = null, ?string $error = null): void
    {
        $log = new EmailLog(
            mb_substr($this->recipients($message, $envelope), 0, 255),
            mb_substr($message instanceof Email ? (string) $message->getSubject() : '', 0, 255),
            $status,
            $messageId,
            $error,
            $this->clock->now(),
        );

        try {
            $this->repository->record($log);
        } catch (\Throwable $exception) {
            // The journal is a diagnostic aid: failing to write it must not fail the delivery.
            $this->logger->error('E-mail journal could not be written.', ['exception' => $exception]);
        }
    }

    private function recipients(RawMessage $message, ?Envelope $envelope): string
    {
        $addresses = $envelope?->getRecipients() ?? ($message instanceof Email ? $message->getTo() : []);

        return implode(', ', array_map(static fn (Address $address): string => $address->getAddress(), $addresses));
    }
}
