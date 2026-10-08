<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\BoardColumn;
use App\Entity\Category;
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
     * Required: appends the task at the bottom of the cell, like TaskCreator does
     * (Foundry adds the task to the collections of the column and the category itself).
     * Without $category, the first category of the project is used.
     */
    public function inColumn(BoardColumn $column, ?Category $category = null): self
    {
        $project = $column->getProject();
        $category ??= $project->getCategories()->first() ?: throw new \LogicException('The project has no category.');

        return $this
            ->with(['column' => $column, 'category' => $category, 'createdBy' => $project->getOwner()])
            // Evaluated for each task, so many() produces positions 0, 1, 2…
            ->with(static fn (): array => ['position' => \count($column->getTasksIn($category))]);
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
