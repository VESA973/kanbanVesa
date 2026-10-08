<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\BoardColumn;
use App\Entity\Task;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Task>
 */
final class TaskFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Task::class;
    }

    /**
     * Required: appends the task at the bottom of the column, like TaskCreator does
     * (Foundry adds the task to $column->getTasks() itself).
     */
    public function inColumn(BoardColumn $column): self
    {
        return $this
            ->with(['column' => $column, 'createdBy' => $column->getProject()->getOwner()])
            // Evaluated for each task, so many() produces positions 0, 1, 2…
            ->with(static fn (): array => ['position' => $column->getTasks()->count()]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'title' => rtrim(self::faker()->sentence(4), '.'),
        ];
    }
}
