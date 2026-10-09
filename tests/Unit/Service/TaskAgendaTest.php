<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Service\TaskAgenda;
use PHPUnit\Framework\TestCase;

final class TaskAgendaTest extends TestCase
{
    public function testGroupsTasksByUrgency(): void
    {
        $now = new \DateTimeImmutable('2026-10-06 15:00');
        $overdue = $this->task('2026-10-05');
        $today = $this->task('2026-10-06');
        $upcoming = $this->task('2026-10-20');
        $noDueDate = $this->task(null);
        $recentlyDone = $this->task('2026-10-01', completedAt: '2026-10-04 10:00');
        $longDone = $this->task('2026-09-01', completedAt: '2026-09-02 10:00');

        $groups = TaskAgenda::group([$overdue, $today, $upcoming, $noDueDate, $recentlyDone, $longDone], $now);

        self::assertSame(TaskAgenda::GROUPS, array_keys($groups));
        self::assertSame([$overdue], $groups['overdue']);
        self::assertSame([$today], $groups['today']);
        self::assertSame([$upcoming], $groups['upcoming']);
        self::assertSame([$noDueDate], $groups['no_due_date']);
        self::assertSame([$recentlyDone], $groups['completed'], 'Tasks completed more than 7 days ago are hidden.');
    }

    public function testCompletedTaskIsNeverOverdue(): void
    {
        $task = $this->task('2026-10-01', completedAt: '2026-10-05 09:00');

        self::assertFalse($task->isOverdue(new \DateTimeImmutable('2026-10-06')));
    }

    public function testByProjectKeepsTheOrderOfProjectsAndTasks(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $voirie = new Project('Voirie', $owner)->addColumn('À faire');
        $ecole = new Project('École', $owner)->addColumn('À faire');
        $a = new Task($voirie, 'A', 0, $owner);
        $b = new Task($ecole, 'B', 0, $owner);
        $c = new Task($voirie, 'C', 1, $owner);

        $groups = TaskAgenda::byProject([$a, $b, $c]);

        self::assertSame([$voirie->getProject(), $ecole->getProject()], array_column($groups, 'project'));
        self::assertSame([[$a, $c], [$b]], array_column($groups, 'tasks'));
    }

    private function task(?string $dueDate, ?string $completedAt = null): Task
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $task = new Task(new Project('Projet', $owner)->addColumn('À faire'), 'Tâche', 0, $owner);
        $task->schedule(null === $dueDate ? null : new \DateTimeImmutable($dueDate));
        if (null !== $completedAt) {
            new \ReflectionProperty(Task::class, 'completedAt')->setValue($task, new \DateTimeImmutable($completedAt));
        }

        return $task;
    }
}
