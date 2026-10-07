<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SmtpSettings;
use App\Entity\User;
use App\Mailer\SettingsTransport;
use App\Mailer\SmtpDsnFactory;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends a test e-mail synchronously (not through Messenger) so the administrator
 * immediately sees whether the SMTP settings work, and why not.
 */
final readonly class SmtpTester
{
    public function __construct(
        private SmtpDsnFactory $dsnFactory,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws TransportExceptionInterface with the SMTP server's error
     */
    public function sendTestEmail(SmtpSettings $settings, User $recipient): void
    {
        $email = new Email()
            ->to($recipient->getEmail())
            ->subject($this->translator->trans('admin.smtp.test_email.subject'))
            ->text($this->translator->trans('admin.smtp.test_email.body', ['%name%' => $recipient->getFirstName()]));

        SettingsTransport::forSettings($settings, $this->dsnFactory)->send(...SettingsTransport::withSender($settings, $email, null));
    }
}
