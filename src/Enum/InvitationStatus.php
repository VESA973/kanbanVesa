<?php

declare(strict_types=1);

namespace App\Enum;

enum InvitationStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case EXPIRED = 'expired';

    public function translationKey(): string
    {
        return 'invitation.status.'.$this->value;
    }
}
