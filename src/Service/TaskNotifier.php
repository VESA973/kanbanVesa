<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Task e-mails; Messenger sends them asynchronously (SendEmailMessage is routed to "async").
 */
final readonly class TaskNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function notifyAssigned(Task $task, User $assignee, ?User $assignedBy): void
    {
        $this->send($assignee, $task, 'email.task_assigned.subject', 'email/task_assigned.html.twig', [
            'assignedByName' => $assignedBy?->getFullName(),
        ]);
    }

    public function notifyDueSoon(Task $task, User $assignee): void
    {
        $this->send($assignee, $task, 'email.due_reminder.subject', 'email/due_reminder.html.twig', [
            'dueDate' => $task->getDueDate(),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(User $recipient, Task $task, string $subjectKey, string $template, array $context): void
    {
        $this->mailer->send(new TemplatedEmail()
            ->to($recipient->getEmail())
            ->subject($this->translator->trans($subjectKey, ['%task%' => $task->getTitle()]))
            ->htmlTemplate($template)
            ->context($context + [
                'firstName' => $recipient->getFirstName(),
                'taskTitle' => $task->getTitle(),
                'taskId' => $task->getId(),
                'projectName' => $task->getProject()->getName(),
            ]));
    }
}
