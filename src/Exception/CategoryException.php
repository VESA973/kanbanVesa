<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * The message is a translation key shown to the user.
 */
final class CategoryException extends \DomainException
{
    public static function lastCategory(): self
    {
        return new self('category.error.last');
    }

    public static function invalidTarget(): self
    {
        return new self('category.error.invalid_target');
    }
}
