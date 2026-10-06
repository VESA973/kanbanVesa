<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use App\Form\Data\TaskData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class TaskUpdater
{
    public function __construct(
        private TaskMover $taskMover,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Choosing another column from the form moves the task to the bottom of it.
     */
    public function update(Task $task, TaskData $data): void
    {
        $this->logChanges($task, $data);

        $task->update($data->title, $data->description);
        $task->assignTo($data->assignee);
        $task->schedule($data->dueDate);
        $task->prioritize($data->priority);

        if ($data->column !== $task->getColumn()) {
            $this->taskMover->moveToEnd($task, $data->column);

            return;
        }

        $this->entityManager->flush();
    }

    private function logChanges(Task $task, TaskData $data): void
    {
        if ($task->getAssignee() !== $data->assignee) {
            $action = null === $data->assignee ? ActivityAction::TASK_UNASSIGNED : ActivityAction::TASK_ASSIGNED;
            $this->log($task, $action, ['assignee' => $data->assignee?->getFullName() ?? '']);
        }

        if ($this->detailsChanged($task, $data)) {
            $this->log($task, ActivityAction::TASK_UPDATED);
        }
    }

    private function detailsChanged(Task $task, TaskData $data): bool
    {
        return $task->getTitle() !== $data->title
            || $task->getDescription() !== (trim((string) $data->description) ?: null)
            || $task->getDueDate()?->format('Y-m-d') !== $data->dueDate?->format('Y-m-d')
            || $task->getPriority() !== $data->priority;
    }

    /**
     * @param array<string, string> $payload
     */
    private function log(Task $task, ActivityAction $action, array $payload = []): void
    {
        $this->dispatcher->dispatch(new ProjectActivityEvent($task->getProject(), $action, $task->getTitle(), $payload));
    }
}
