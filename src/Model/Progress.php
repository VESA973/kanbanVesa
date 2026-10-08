<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Completed tasks out of a total (a project or a whole program).
 */
final readonly class Progress
{
    public function __construct(
        public int $total = 0,
        public int $completed = 0,
    ) {
    }

    public function isEmpty(): bool
    {
        return 0 === $this->total;
    }

    public function percent(): int
    {
        return $this->isEmpty() ? 0 : (int) floor($this->completed * 100 / $this->total);
    }

    public function add(self $other): self
    {
        return new self($this->total + $other->total, $this->completed + $other->completed);
    }
}
