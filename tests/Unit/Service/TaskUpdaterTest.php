<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Enum\ProjectRole;
use App\Enum\TaskPriority;
use App\Form\Data\TaskData;
use App\Service\TaskCompleter;
use App\Service\TaskMover;
use App\Service\TaskUpdater;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class TaskUpdaterTest extends TestCase
{
    private User $owner;
    private Project $project;
    private Task $task;
    private RecordingDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->owner = new User('owner@example.com', 'Olivia', 'Owner');
        $this->project = new Project('Projet', $this->owner);
        $column = $this->project->addColumn('À faire');
        $this->task = new Task($column, 'Tâche', 0, $this->owner);
        $column->getTasks()->add($this->task);
        $this->dispatcher = new RecordingDispatcher();
    }

    public function testAssigningLogsOnlyTheAssignment(): void
    {
        $alex = new User('alex@example.com', 'Alex', 'Martin');
        $this->project->addMember($alex, ProjectRole::EDITOR);
        $data = TaskData::fromTask($this->task);
        $data->assignees = [$alex];

        $this->updater()->update($this->task, $data);

        self::assertSame([$alex], $this->task->getAssignees()->getValues());
        self::assertSame([ActivityAction::TASK_ASSIGNED], $this->dispatcher->actions());
        self::assertSame(['assignee' => 'Alex Martin'], $this->dispatcher->last()->payload);
        self::assertSame($alex, $this->dispatcher->last()->assignee);
    }

    public function testReassigningLogsEachAddedAndRemovedAssignee(): void
    {
        $alex = new User('alex@example.com', 'Alex', 'Martin');
        $sam = new User('sam@example.com', 'Sam', 'Durand');
        $this->task->assign($this->owner);
        $this->task->assign($alex);
        $data = TaskData::fromTask($this->task);
        $data->assignees = [$alex, $sam];

        $this->updater()->update($this->task, $data);

        self::assertSame([$alex, $sam], $this->task->getAssignees()->getValues());
        self::assertSame([ActivityAction::TASK_UNASSIGNED, ActivityAction::TASK_ASSIGNED], $this->dispatcher->actions());
        self::assertSame($sam, $this->dispatcher->last()->assignee);
    }

    public function testChangingDetailsLogsAnUpdate(): void
    {
        $data = TaskData::fromTask($this->task);
        $data->dueDate = new \DateTimeImmutable('2026-12-24 18:30');
        $data->priority = TaskPriority::URGENT;

        $this->updater()->update($this->task, $data);

        self::assertSame('2026-12-24 00:00', $this->task->getDueDate()?->format('Y-m-d H:i'));
        self::assertSame(TaskPriority::URGENT, $this->task->getPriority());
        self::assertSame([ActivityAction::TASK_UPDATED], $this->dispatcher->actions());
    }

    public function testSavingWithoutChangesLogsNothing(): void
    {
        $this->updater()->update($this->task, TaskData::fromTask($this->task));

        self::assertSame([], $this->dispatcher->actions());
    }

    public function testCompleterTogglesAndLogs(): void
    {
        $completer = new TaskCompleter($this->createStub(EntityManagerInterface::class), $this->dispatcher);

        self::assertTrue($completer->toggle($this->task));
        self::assertNotNull($this->task->getCompletedAt());
        self::assertFalse($completer->toggle($this->task));
        self::assertSame([ActivityAction::TASK_COMPLETED, ActivityAction::TASK_REOPENED], $this->dispatcher->actions());
    }

    private function updater(): TaskUpdater
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);

        return new TaskUpdater(new TaskMover($entityManager, $this->dispatcher), $entityManager, $this->dispatcher);
    }
}
