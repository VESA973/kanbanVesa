<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\User;

/**
 * Tasks completed per member and per week, oldest week first.
 */
final readonly class WeeklyCompletions
{
    /**
     * @param list<\DateTimeImmutable>                                $weeks Monday of each week
     * @param list<array{user: ?User, counts: list<int>, total: int}> $rows  one row per member ($user null: unassigned)
     */
    public function __construct(
        public array $weeks,
        public array $rows,
    ) {
    }

    public function max(): int
    {
        $max = 0;
        foreach ($this->rows as $row) {
            $max = max($max, ...$row['counts']);
        }

        return $max;
    }

    public function isEmpty(): bool
    {
        return 0 === array_sum(array_column($this->rows, 'total'));
    }
}
