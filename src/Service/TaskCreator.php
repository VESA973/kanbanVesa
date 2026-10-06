<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BoardColumn;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;

final readonly class TaskCreator
{
    public function __construct(
        private TaskRepository $taskRepository,
    ) {
    }

    /**
     * New tasks are appended at the bottom of the column.
     */
    public function create(BoardColumn $column, string $title, User $author): Task
    {
        $task = new Task($column, $title, $column->getTasks()->count(), $author);
        $column->getTasks()->add($task);
        $this->taskRepository->save($task);

        return $task;
    }
}
