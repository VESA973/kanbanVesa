<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Entity\SmtpSettings;
use App\Enum\SmtpEncryption;
use App\Service\SecretBox;

/**
 * Turns the administrator's SMTP settings into a Symfony Mailer DSN.
 */
final readonly class SmtpDsnFactory
{
    public function __construct(
        private SecretBox $secretBox,
    ) {
    }

    public function create(SmtpSettings $settings): string
    {
        $scheme = SmtpEncryption::SSL === $settings->getEncryption() ? 'smtps' : 'smtp';
        $credentials = $this->credentials($settings);
        // Without encryption, Symfony would still try STARTTLS when the server offers it.
        $query = SmtpEncryption::NONE === $settings->getEncryption() ? '?auto_tls=false' : '';

        return \sprintf('%s://%s%s:%d%s', $scheme, $credentials, $settings->getHost(), $settings->getPort(), $query);
    }

    private function credentials(SmtpSettings $settings): string
    {
        $username = $settings->getUsername();
        if (null === $username) {
            return '';
        }

        $encryptedPassword = $settings->getEncryptedPassword();
        $password = null === $encryptedPassword ? '' : ':'.rawurlencode($this->secretBox->decrypt($encryptedPassword));

        return rawurlencode($username).$password.'@';
    }
}
