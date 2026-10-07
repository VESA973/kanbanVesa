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
     * E-mails the assignee of every open task due tomorrow, once per due date.
     *
     * @return int the number of reminders sent
     */
    public function remindTasksDueTomorrow(): int
    {
        $tasks = $this->taskRepository->findNeedingDueReminder($this->clock->now()->modify('+1 day'));

        foreach ($tasks as $task) {
            $assignee = $task->getAssignee() ?? throw new \LogicException('Only assigned tasks get a reminder.');
            $this->notifier->notifyDueSoon($task, $assignee);
            $task->markDueReminderAsSent();
        }

        $this->entityManager->flush();

        return \count($tasks);
    }
}
