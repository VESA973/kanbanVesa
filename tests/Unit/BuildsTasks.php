<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\BoardColumn;
use App\Entity\Category;
use App\Entity\Task;
use App\Entity\User;

/**
 * Builds in-memory tasks the way TaskCreator does: at the bottom of their cell,
 * in the first category of the project unless another one is given.
 */
trait BuildsTasks
{
    private static function newTask(BoardColumn $column, string $title = 'Tâche', ?User $author = null, ?Category $category = null): Task
    {
        $project = $column->getProject();
        $category ??= $project->getCategories()->first() ?: $project->addCategory('Général');
        $task = new Task($column, $category, $title, \count($column->getTasksIn($category)), $author ?? $project->getOwner());
        $column->getTasks()->add($task);
        $category->getTasks()->add($task);

        return $task;
    }
}
