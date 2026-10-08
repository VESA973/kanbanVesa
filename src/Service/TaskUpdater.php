<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Label;
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
        $assigneeChanged = $task->getAssignee() !== $data->assignee;
        $detailsChanged = $this->detailsChanged($task, $data);

        $task->update($data->title, $data->description);
        $task->assignTo($data->assignee);
        $task->schedule($data->dueDate);
        $task->prioritize($data->priority);
        $task->replaceLabels($data->labels);

        // Dispatched once the task is up to date, so listeners (notifications) see the new values.
        if ($assigneeChanged) {
            $action = null === $data->assignee ? ActivityAction::TASK_UNASSIGNED : ActivityAction::TASK_ASSIGNED;
            $this->log($task, $action, ['assignee' => $data->assignee?->getFullName() ?? '']);
        }
        if ($detailsChanged) {
            $this->log($task, ActivityAction::TASK_UPDATED);
        }

        $this->save($task, $data);
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
    private function log(Task $task, ActivityAction $action, array $payload = []): void
    {
        $this->dispatcher->dispatch(new ProjectActivityEvent($task->getProject(), $action, $task->getTitle(), $payload, $task));
    }
}
