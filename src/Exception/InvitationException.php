<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * The message is a translation key shown to the user.
 */
final class InvitationException extends \DomainException
{
    public static function notFound(): self
    {
        return new self('invitation.error.not_found');
    }

    public static function alreadyUsed(): self
    {
        return new self('invitation.error.already_used');
    }

    public static function expired(): self
    {
        return new self('invitation.error.expired');
    }

    public static function emailMismatch(): self
    {
        return new self('invitation.error.email_mismatch');
    }

    public static function alreadyMember(): self
    {
        return new self('invitation.error.already_member');
    }
}
