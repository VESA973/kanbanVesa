<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use App\Repository\TaskRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class TaskRemover
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function remove(Task $task): void
    {
        $this->dispatcher->dispatch(new ProjectActivityEvent($task->getProject(), ActivityAction::TASK_DELETED, $task->getTitle(), [
            'column' => $task->getColumn()->getName(),
        ]));
        $this->taskRepository->remove($task);
    }
}
