<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\BoardColumn;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Service\TaskMover;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class TaskMoverTest extends TestCase
{
    private User $owner;
    private Project $project;

    protected function setUp(): void
    {
        $this->owner = new User('owner@example.com', 'Olivia', 'Owner');
        $this->project = new Project('Projet', $this->owner);
    }

    public function testReordersTasksInsideTheSameColumn(): void
    {
        $column = $this->project->addColumn('À faire');
        [$a, $b, $c] = $this->tasks($column, 'a', 'b', 'c');

        $position = $this->mover()->move($c, $column, 0);

        self::assertSame(0, $position);
        self::assertSame([1, 2, 0], [$a->getPosition(), $b->getPosition(), $c->getPosition()]);
    }

    public function testMovesATaskToAnotherColumnAndClosesTheGap(): void
    {
        $todo = $this->project->addColumn('À faire');
        $done = $this->project->addColumn('Terminé');
        [$a, $b] = $this->tasks($todo, 'a', 'b');
        [$x] = $this->tasks($done, 'x');

        $position = $this->mover()->move($a, $done, 1);

        self::assertSame(1, $position);
        self::assertSame($done, $a->getColumn());
        self::assertSame(0, $b->getPosition(), 'The source column has no gap.');
        self::assertSame([0, 1], [$x->getPosition(), $a->getPosition()]);
        self::assertTrue($done->getTasks()->contains($a));
        self::assertFalse($todo->getTasks()->contains($a));
    }

    public function testPositionBeyondTheEndIsClamped(): void
    {
        $todo = $this->project->addColumn('À faire');
        $done = $this->project->addColumn('Terminé');
        [$a] = $this->tasks($todo, 'a');
        $this->tasks($done, 'x', 'y');

        self::assertSame(2, $this->mover()->moveToEnd($a, $done));
    }

    public function testRefusesToMoveATaskToAnotherProject(): void
    {
        [$task] = $this->tasks($this->project->addColumn('À faire'), 'a');
        $otherColumn = new Project('Autre', $this->owner)->addColumn('À faire');

        $this->expectException(\InvalidArgumentException::class);

        $this->mover()->move($task, $otherColumn, 0);
    }

    private function mover(): TaskMover
    {
        return new TaskMover($this->createStub(EntityManagerInterface::class));
    }

    /**
     * @return list<Task>
     */
    private function tasks(BoardColumn $column, string ...$titles): array
    {
        $tasks = [];
        foreach ($titles as $title) {
            $task = new Task($column, $title, $column->getTasks()->count(), $this->owner);
            $column->getTasks()->add($task);
            $tasks[] = $task;
        }

        return $tasks;
    }
}
