<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Board filters read from the query string (?assignee=12&label=3&due=overdue).
 */
final readonly class BoardFilter
{
    public const string UNASSIGNED = 'none';

    /** A user id, or "none" for unassigned tasks. */
    #[Assert\Regex('/^(\d+|none)$/')]
    public ?string $assignee;

    #[Assert\Regex('/^\d+$/')]
    public ?string $label;

    #[Assert\Choice(choices: ['overdue', 'week', 'none'])]
    public ?string $due;

    /**
     * The filter form sends empty strings for "all": they mean no filter.
     */
    public function __construct(?string $assignee = null, ?string $label = null, ?string $due = null)
    {
        $this->assignee = '' === $assignee ? null : $assignee;
        $this->label = '' === $label ? null : $label;
        $this->due = '' === $due ? null : $due;
    }

    public function isActive(): bool
    {
        return null !== $this->assignee || null !== $this->label || null !== $this->due;
    }

    public function matches(Task $task, \DateTimeInterface $now): bool
    {
        return $this->matchesAssignee($task) && $this->matchesLabel($task) && $this->matchesDueDate($task, $now);
    }

    private function matchesAssignee(Task $task): bool
    {
        return match ($this->assignee) {
            null => true,
            self::UNASSIGNED => $task->getAssignees()->isEmpty(),
            default => $task->getAssignees()->exists(fn (int $key, User $user): bool => (string) $user->getId() === $this->assignee),
        };
    }

    private function matchesLabel(Task $task): bool
    {
        if (null === $this->label) {
            return true;
        }

        foreach ($task->getLabels() as $label) {
            if ((string) $label->getId() === $this->label) {
                return true;
            }
        }

        return false;
    }

    private function matchesDueDate(Task $task, \DateTimeInterface $now): bool
    {
        $today = \DateTimeImmutable::createFromInterface($now)->setTime(0, 0);
        $dueDate = $task->getDueDate();

        return match ($this->due) {
            null => true,
            'none' => null === $dueDate,
            'overdue' => $task->isOverdue($today),
            default => null !== $dueDate && !$task->isCompleted() && $dueDate >= $today && $dueDate <= $today->modify('+7 days'),
        };
    }
}
