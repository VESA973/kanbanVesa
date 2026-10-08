<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TaskTableColumn;
use App\Enum\TableColumnType;
use App\Exception\TaskTableException;

/**
 * Turns what was typed in a cell into the value stored for its column type
 * (null empties the cell). Server-side validation of every cell.
 */
final class TableCellNormalizer
{
    public const int MAX_TEXT_LENGTH = 500;

    /**
     * @throws TaskTableException when the value does not fit the column type
     */
    public static function normalize(TaskTableColumn $column, ?string $raw): string|int|float|bool|null
    {
        $raw = trim((string) $raw);

        return match ($column->getType()) {
            TableColumnType::CHECKBOX => \in_array(mb_strtolower($raw), ['1', 'true', 'on', 'oui'], true) ? true : null,
            default => '' === $raw ? null : self::normalizeFilled($column, $raw),
        };
    }

    private static function normalizeFilled(TaskTableColumn $column, string $raw): string|int|float
    {
        return match ($column->getType()) {
            TableColumnType::NUMBER => self::number($raw),
            TableColumnType::DATE => self::date($raw),
            TableColumnType::MEMBER => self::member($column, $raw),
            default => mb_strlen($raw) > self::MAX_TEXT_LENGTH ? throw TaskTableException::tooLong() : $raw,
        };
    }

    /**
     * Accepts the French way of writing numbers: "1 234,5".
     */
    private static function number(string $raw): int|float
    {
        $normalized = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], $raw);
        if (!is_numeric($normalized)) {
            throw TaskTableException::invalidValue();
        }

        $number = (float) $normalized;

        return floor($number) === $number && abs($number) < \PHP_INT_MAX ? (int) $number : $number;
    }

    private static function date(string $raw): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if (false === $date || $date->format('Y-m-d') !== $raw) {
            throw TaskTableException::invalidValue();
        }

        return $raw;
    }

    private static function member(TaskTableColumn $column, string $raw): int
    {
        foreach ($column->getTable()->getTask()->getProject()->getMembers() as $member) {
            if ((string) $member->getUser()->getId() === $raw) {
                return (int) $raw;
            }
        }

        throw TaskTableException::notAMember();
    }
}
