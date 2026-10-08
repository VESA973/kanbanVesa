<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\BoardColumn;
use App\Entity\Category;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Service\TaskMover;
use App\Tests\Unit\BuildsTasks;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class TaskMoverTest extends TestCase
{
    use BuildsTasks;

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
        self::assertSame([], $this->dispatcher->actions(), 'Reordering inside a column is not logged.');
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
        self::assertSame(['from' => 'À faire', 'to' => 'Terminé'], $this->dispatcher->last()->payload);
    }

    public function testPositionBeyondTheEndIsClamped(): void
    {
        $todo = $this->project->addColumn('À faire');
        $done = $this->project->addColumn('Terminé');
        [$a] = $this->tasks($todo, 'a');
        $this->tasks($done, 'x', 'y');

        self::assertSame(2, $this->mover()->moveToEnd($a, $done));
    }

    public function testMovesATaskToAnotherCategoryOfTheSameColumn(): void
    {
        $todo = $this->project->addColumn('À faire');
        $design = $this->project->addCategory('Design');
        $dev = $this->project->addCategory('Développement');
        [$a, $b] = $this->tasksIn($todo, $design, 'a', 'b');
        [$x] = $this->tasksIn($todo, $dev, 'x');

        $position = $this->mover()->move($a, $todo, 0, $dev);

        self::assertSame(0, $position);
        self::assertSame($dev, $a->getCategory());
        self::assertSame([0, 1], [$a->getPosition(), $x->getPosition()], 'Positions are counted within the target cell.');
        self::assertSame(0, $b->getPosition(), 'The source cell has no gap.');
        self::assertTrue($dev->getTasks()->contains($a));
        self::assertFalse($design->getTasks()->contains($a));
        self::assertSame(['from' => 'Design / À faire', 'to' => 'Développement / À faire'], $this->dispatcher->last()->payload);
    }

    public function testRefusesToMoveATaskToACategoryOfAnotherProject(): void
    {
        $todo = $this->project->addColumn('À faire');
        [$task] = $this->tasks($todo, 'a');
        $otherCategory = new Project('Autre', $this->owner)->addCategory('Général');

        $this->expectException(\InvalidArgumentException::class);

        $this->mover()->move($task, $todo, 0, $otherCategory);
    }

    public function testRefusesToMoveATaskToAnotherProject(): void
    {
        [$task] = $this->tasks($this->project->addColumn('À faire'), 'a');
        $otherColumn = new Project('Autre', $this->owner)->addColumn('À faire');

        $this->expectException(\InvalidArgumentException::class);

        $this->mover()->move($task, $otherColumn, 0);
    }

    private RecordingDispatcher $dispatcher;

    private function mover(): TaskMover
    {
        $this->dispatcher = new RecordingDispatcher();

        return new TaskMover($this->createStub(EntityManagerInterface::class), $this->dispatcher);
    }

    /**
     * @return list<Task>
     */
    private function tasks(BoardColumn $column, string ...$titles): array
    {
        return array_values(array_map(static fn (string $title): Task => self::newTask($column, $title), $titles));
    }

    /**
     * @return list<Task>
     */
    private function tasksIn(BoardColumn $column, Category $category, string ...$titles): array
    {
        return array_values(array_map(static fn (string $title): Task => self::newTask($column, $title, null, $category), $titles));
    }
}
