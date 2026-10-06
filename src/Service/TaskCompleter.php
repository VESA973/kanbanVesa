<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class TaskCompleter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @return bool whether the task is now completed
     */
    public function toggle(Task $task): bool
    {
        if ($task->isCompleted()) {
            $task->reopen();
        } else {
            $task->complete();
        }

        $action = $task->isCompleted() ? ActivityAction::TASK_COMPLETED : ActivityAction::TASK_REOPENED;
        $this->dispatcher->dispatch(new ProjectActivityEvent($task->getProject(), $action, $task->getTitle()));
        $this->entityManager->flush();

        return $task->isCompleted();
    }
}
