<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class DueDateReminder
{
    public function __construct(
        private TaskRepository $taskRepository,
        private TaskNotifier $notifier,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * E-mails every assignee of each open task due tomorrow, once per due date.
     *
     * @return int the number of reminders sent
     */
    public function remindTasksDueTomorrow(): int
    {
        $sent = 0;
        foreach ($this->taskRepository->findNeedingDueReminder($this->clock->now()->modify('+1 day')) as $task) {
            foreach ($task->getAssignees() as $assignee) {
                $this->notifier->notifyDueSoon($task, $assignee);
                ++$sent;
            }
            $task->markDueReminderAsSent();
        }

        $this->entityManager->flush();

        return $sent;
    }
}
