<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\User;

/**
 * Task counters of one member in a project ($user is null for unassigned tasks).
 */
final readonly class MemberProgress
{
    public function __construct(
        public ?User $user,
        public int $total = 0,
        public int $completed = 0,
        public int $overdue = 0,
    ) {
    }

    public function open(): int
    {
        return $this->total - $this->completed;
    }

    public function completionRate(): int
    {
        return 0 === $this->total ? 0 : (int) round($this->completed * 100 / $this->total);
    }
}
