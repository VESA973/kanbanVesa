<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * The message is a translation key shown to the user.
 */
final class ProgramException extends \DomainException
{
    public static function notEmpty(): self
    {
        return new self('program.error.not_empty');
    }
}
