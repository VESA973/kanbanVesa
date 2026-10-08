<?php

declare(strict_types=1);

namespace App\Enum;

enum EmailStatus: string
{
    case SENT = 'sent';
    case FAILED = 'failed';
    /** No SMTP server is configured in the administration: the e-mail was dropped. */
    case NOT_CONFIGURED = 'not_configured';

    public function translationKey(): string
    {
        return 'admin.emails.status.'.$this->value;
    }
}
