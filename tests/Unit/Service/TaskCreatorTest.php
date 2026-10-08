<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\TaskCreator;
use PHPUnit\Framework\TestCase;

final class TaskCreatorTest extends TestCase
{
    public function testAppendsTasksAtTheBottomOfTheirCell(): void
    {
        $author = new User('owner@example.com', 'Olivia', 'Owner');
        $project = new Project('Projet', $author);
        $column = $project->addColumn('À faire');
        $general = $project->addCategory('Général');
        $design = $project->addCategory('Design');
        $repository = $this->createMock(TaskRepository::class);
        $repository->expects($this->exactly(3))->method('save');
        $dispatcher = new RecordingDispatcher();
        $creator = new TaskCreator($repository, $dispatcher);

        $first = $creator->create($column, $general, 'Première', $author);
        $second = $creator->create($column, $general, 'Deuxième', $author);
        $other = $creator->create($column, $design, 'Maquette', $author);

        self::assertSame([0, 1], [$first->getPosition(), $second->getPosition()]);
        self::assertSame(0, $other->getPosition(), 'Positions are counted within the cell (column + category).');
        self::assertSame($author, $second->getCreatedBy());
        self::assertSame($design, $other->getCategory());
        self::assertCount(3, $column->getTasks());
        self::assertCount(2, $general->getTasks());
        self::assertSame(['column' => 'Design / À faire'], $dispatcher->last()->payload);
    }
}
