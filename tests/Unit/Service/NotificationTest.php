<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use App\EventSubscriber\TaskAssignedSubscriber;
use App\Repository\TaskRepository;
use App\Service\DueDateReminder;
use App\Service\TaskNotifier;
use App\Tests\Unit\BuildsTasks;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class NotificationTest extends TestCase
{
    use BuildsTasks;

    private User $owner;
    private User $alex;
    private Task $task;

    protected function setUp(): void
    {
        $this->owner = new User('owner@example.com', 'Olivia', 'Owner');
        $this->alex = new User('alex@example.com', 'Alex', 'Martin');
        $this->task = self::newTask(new Project('Projet', $this->owner)->addColumn('À faire'), 'Tâche', $this->owner);
    }

    public function testAssigneeIsNotifiedWhenSomeoneElseAssignsThem(): void
    {
        $this->task->assignTo($this->alex);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->with($this->callback(
            fn (TemplatedEmail $email): bool => 'alex@example.com' === $email->getTo()[0]->getAddress()
                && 'Olivia Owner' === $email->getContext()['assignedByName'],
        ));

        $this->subscriber($mailer, $this->owner)->onProjectActivity($this->assigned());
    }

    public function testNoNotificationWhenAssigningOneself(): void
    {
        $this->task->assignTo($this->alex);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $this->subscriber($mailer, $this->alex)->onProjectActivity($this->assigned());
    }

    public function testOtherActivitiesSendNothing(): void
    {
        $this->task->assignTo($this->alex);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $this->subscriber($mailer, $this->owner)->onProjectActivity(new ProjectActivityEvent($this->task->getProject(), ActivityAction::TASK_UPDATED, 'Tâche', task: $this->task));
    }

    public function testDueReminderIsSentOncePerDueDate(): void
    {
        $this->task->assignTo($this->alex);
        $this->task->schedule(new \DateTimeImmutable('2026-10-08'));
        $clock = new MockClock('2026-10-07 07:00');
        $repository = $this->createMock(TaskRepository::class);
        $repository->expects($this->once())->method('findNeedingDueReminder')
            ->with($this->callback(static fn (\DateTimeImmutable $date): bool => '2026-10-08' === $date->format('Y-m-d')))
            ->willReturn([$this->task]);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $sent = new DueDateReminder($repository, $this->notifier($mailer), $this->createStub(EntityManagerInterface::class), $clock)->remindTasksDueTomorrow();

        self::assertSame(1, $sent);
        self::assertNotNull($this->task->getDueReminderSentAt());

        $this->task->schedule(new \DateTimeImmutable('2026-10-15'));
        self::assertNull($this->task->getDueReminderSentAt(), 'A new due date gets a new reminder.');
    }

    private function assigned(): ProjectActivityEvent
    {
        return new ProjectActivityEvent($this->task->getProject(), ActivityAction::TASK_ASSIGNED, 'Tâche', ['assignee' => 'Alex Martin'], $this->task);
    }

    private function subscriber(MailerInterface $mailer, User $actor): TaskAssignedSubscriber
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($actor);

        return new TaskAssignedSubscriber($this->notifier($mailer), $security);
    }

    private function notifier(MailerInterface $mailer): TaskNotifier
    {
        return new TaskNotifier($mailer, $this->createStub(TranslatorInterface::class));
    }
}
