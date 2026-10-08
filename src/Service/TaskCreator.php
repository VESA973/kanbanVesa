<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BoardColumn;
use App\Entity\Category;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use App\Repository\TaskRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class TaskCreator
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * New tasks are appended at the bottom of their cell (the column within the category).
     */
    public function create(BoardColumn $column, Category $category, string $title, User $author): Task
    {
        $task = new Task($column, $category, $title, \count($column->getTasksIn($category)), $author);
        $column->getTasks()->add($task);
        $category->getTasks()->add($task);
        $this->dispatcher->dispatch(new ProjectActivityEvent($column->getProject(), ActivityAction::TASK_CREATED, $title, ['column' => \sprintf('%s / %s', $category->getName(), $column->getName())]));
        $this->taskRepository->save($task);

        return $task;
    }
}
