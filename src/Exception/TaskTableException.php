<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * The message is a translation key shown to the user.
 */
final class TaskTableException extends \DomainException
{
    public static function invalidValue(): self
    {
        return new self('task_table.error.invalid_value');
    }

    public static function tooLong(): self
    {
        return new self('task_table.error.too_long');
    }

    public static function notAMember(): self
    {
        return new self('task_table.error.not_a_member');
    }

    public static function tooManyColumns(): self
    {
        return new self('task_table.error.too_many_columns');
    }

    public static function tooManyRows(): self
    {
        return new self('task_table.error.too_many_rows');
    }

    public static function lastColumn(): self
    {
        return new self('task_table.error.last_column');
    }
}
