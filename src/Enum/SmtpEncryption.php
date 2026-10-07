<?php

declare(strict_types=1);

namespace App\Enum;

enum SmtpEncryption: string
{
    /** Plain connection upgraded with STARTTLS (usually port 587). */
    case STARTTLS = 'starttls';
    /** Implicit TLS from the start (usually port 465). */
    case SSL = 'ssl';
    /** No encryption: only for a server on the same machine or network. */
    case NONE = 'none';

    public function translationKey(): string
    {
        return 'admin.smtp.encryption.'.$this->value;
    }
}
