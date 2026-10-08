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
    public function testAppendsTasksAtTheBottomOfTheColumn(): void
    {
        $author = new User('owner@example.com', 'Olivia', 'Owner');
        $column = new Project('Projet', $author)->addColumn('À faire');
        $repository = $this->createMock(TaskRepository::class);
        $repository->expects($this->exactly(2))->method('save');
        $dispatcher = new RecordingDispatcher();
        $creator = new TaskCreator($repository, $dispatcher);

        $first = $creator->create($column, 'Première', $author);
        $second = $creator->create($column, 'Deuxième', $author);

        self::assertSame([0, 1], [$first->getPosition(), $second->getPosition()]);
        self::assertSame($author, $second->getCreatedBy());
        self::assertCount(2, $column->getTasks());
        self::assertSame(['column' => 'À faire'], $dispatcher->last()->payload);
    }
}
