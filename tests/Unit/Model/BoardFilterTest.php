<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Entity\Label;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\ProjectColor;
use App\Model\BoardFilter;
use App\Tests\Unit\BuildsTasks;
use PHPUnit\Framework\TestCase;

final class BoardFilterTest extends TestCase
{
    use BuildsTasks;

    private User $alex;
    private Project $project;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->alex = new User('alex@example.com', 'Alex', 'Martin');
        new \ReflectionProperty(User::class, 'id')->setValue($this->alex, 7);
        $this->project = new Project('Projet', $this->alex);
        $this->now = new \DateTimeImmutable('2026-10-07 10:00');
    }

    public function testEmptyValuesFromTheFormMeanNoFilter(): void
    {
        $filter = new BoardFilter('', '', '');

        self::assertFalse($filter->isActive());
        self::assertTrue($filter->matches($this->task(), $this->now));
    }

    public function testFiltersByAssignee(): void
    {
        $mine = $this->task();
        $mine->assignTo($this->alex);
        $nobodys = $this->task();

        self::assertTrue(new BoardFilter(assignee: '7')->matches($mine, $this->now));
        self::assertFalse(new BoardFilter(assignee: '7')->matches($nobodys, $this->now));
        self::assertTrue(new BoardFilter(assignee: 'none')->matches($nobodys, $this->now));
    }

    public function testFiltersByLabel(): void
    {
        $bug = new Label($this->project, 'Bug', ProjectColor::ROSE);
        new \ReflectionProperty(Label::class, 'id')->setValue($bug, 3);
        $task = $this->task();
        $task->replaceLabels([$bug]);

        self::assertTrue(new BoardFilter(label: '3')->matches($task, $this->now));
        self::assertFalse(new BoardFilter(label: '4')->matches($task, $this->now));
    }

    public function testFiltersByDueDate(): void
    {
        $overdue = $this->task('2026-10-01');
        $thisWeek = $this->task('2026-10-10');
        $later = $this->task('2026-11-30');
        $none = $this->task();

        self::assertSame([true, false, false, false], $this->matchingResults('overdue', $overdue, $thisWeek, $later, $none));
        self::assertSame([false, true, false, false], $this->matchingResults('week', $overdue, $thisWeek, $later, $none));
        self::assertSame([false, false, false, true], $this->matchingResults('none', $overdue, $thisWeek, $later, $none));
    }

    /**
     * @return list<bool>
     */
    private function matchingResults(string $due, Task ...$tasks): array
    {
        return array_map(fn (Task $task): bool => new BoardFilter(due: $due)->matches($task, $this->now), array_values($tasks));
    }

    private function task(?string $dueDate = null): Task
    {
        $task = self::newTask($this->project->getColumns()->first() ?: $this->project->addColumn('À faire'), 'Tâche', $this->alex);
        $task->schedule(null === $dueDate ? null : new \DateTimeImmutable($dueDate));

        return $task;
    }
}
