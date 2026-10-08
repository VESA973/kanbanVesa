<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Entity\SmtpSettings;
use App\Repository\SmtpSettingsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * Mailer transport ("settings://default") that sends through the SMTP server configured
 * by an administrator. Settings are read at each send, so the Messenger worker picks up
 * changes without being restarted.
 */
final readonly class SettingsTransport implements TransportInterface
{
    public function __construct(
        private SmtpSettingsRepository $settingsRepository,
        private SmtpDsnFactory $dsnFactory,
        private LoggerInterface $logger,
        private EmailJournal $journal,
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $settings = $this->settingsRepository->findCurrent();
        if (null === $settings || !$settings->isConfigured()) {
            $this->logger->warning('E-mail not sent: no SMTP server is configured in the administration.');
            $this->journal->notConfigured($message, $envelope);

            return null;
        }

        [$message, $envelope] = self::withSender($settings, $message, $envelope);
        try {
            $sent = self::forSettings($settings, $this->dsnFactory)->send($message, $envelope);
        } catch (TransportExceptionInterface $exception) {
            // Logged, then rethrown so Messenger retries and finally keeps the message in "failed".
            $this->journal->failed($message, $envelope, $exception);

            throw $exception;
        }

        if (null !== $sent) {
            $this->journal->sent($sent);
        }

        return $sent;
    }

    /**
     * Also used to test the settings from the administration, before saving them.
     */
    public static function forSettings(SmtpSettings $settings, SmtpDsnFactory $dsnFactory): TransportInterface
    {
        return Transport::fromDsn($dsnFactory->create($settings));
    }

    /**
     * The configured sender replaces the default "From" (MAILER_FROM).
     *
     * @return array{RawMessage, ?Envelope}
     */
    public static function withSender(SmtpSettings $settings, RawMessage $message, ?Envelope $envelope): array
    {
        if (!$message instanceof Message) {
            return [$message, $envelope];
        }

        $sender = new Address($settings->getFromAddress(), $settings->getFromName() ?? '');
        $headers = $message->getHeaders();
        $headers->remove('From');
        $headers->addMailboxListHeader('From', [$sender]);

        $recipients = ($envelope ?? Envelope::create($message))->getRecipients();

        return [$message, new Envelope($sender, $recipients)];
    }

    public function __toString(): string
    {
        return 'settings://default';
    }
}
