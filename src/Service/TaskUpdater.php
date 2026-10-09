<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Label;
use App\Entity\Task;
use App\Entity\User;
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
        $detailsChanged = $this->detailsChanged($task, $data);

        $task->update($data->title, $data->description);
        $task->schedule($data->dueDate);
        $task->prioritize($data->priority);
        $task->replaceLabels($data->labels);

        // Dispatched once the task is up to date, so listeners (notifications) see the new values.
        $this->reassign($task, $data->assignees);
        if ($detailsChanged) {
            $this->log($task, ActivityAction::TASK_UPDATED);
        }

        $this->save($task, $data);
    }

    /**
     * @param list<User> $assignees
     */
    private function reassign(Task $task, array $assignees): void
    {
        foreach ($task->getAssignees()->getValues() as $current) {
            if (!\in_array($current, $assignees, true)) {
                $task->unassign($current);
                $this->log($task, ActivityAction::TASK_UNASSIGNED, ['assignee' => $current->getFullName()], $current);
            }
        }

        foreach ($assignees as $user) {
            if ($task->assign($user)) {
                $this->log($task, ActivityAction::TASK_ASSIGNED, ['assignee' => $user->getFullName()], $user);
            }
        }
    }

    private function save(Task $task, TaskData $data): void
    {
        if ($data->column !== $task->getColumn()) {
            $this->taskMover->moveToEnd($task, $data->column);

            return;
        }

        $this->entityManager->flush();
    }

    private function detailsChanged(Task $task, TaskData $data): bool
    {
        return $task->getTitle() !== $data->title
            || $task->getDescription() !== (trim((string) $data->description) ?: null)
            || $task->getDueDate()?->format('Y-m-d') !== $data->dueDate?->format('Y-m-d')
            || $task->getPriority() !== $data->priority
            || $this->labelIds($task->getLabels()) !== $this->labelIds($data->labels);
    }

    /**
     * @param iterable<Label> $labels
     *
     * @return list<int|null>
     */
    private function labelIds(iterable $labels): array
    {
        $ids = [];
        foreach ($labels as $label) {
            $ids[] = $label->getId();
        }
        sort($ids);

        return $ids;
    }

    /**
     * @param array<string, string> $payload
     */
    private function log(Task $task, ActivityAction $action, array $payload = [], ?User $assignee = null): void
    {
        $this->dispatcher->dispatch(new ProjectActivityEvent($task->getProject(), $action, $task->getTitle(), $payload, $task, $assignee));
    }
}
