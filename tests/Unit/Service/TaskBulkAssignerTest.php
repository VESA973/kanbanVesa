<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Repository\TaskRepository;
use App\Service\TaskBulkAssigner;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

final class TaskBulkAssignerTest extends TestCase
{
    public function testAddsTheMembersToEveryOpenTaskAndLogsOncePerMember(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $alex = new User('alex@example.com', 'Alex', 'Martin');
        $sam = new User('sam@example.com', 'Sam', 'Durand');
        $project = new Project('Projet', $owner);
        $column = $project->addColumn('À faire');
        $alreadyAlex = new Task($column, 'Déjà à Alex', 0, $owner);
        $alreadyAlex->assign($alex);
        $free = new Task($column, 'Libre', 1, $owner);
        $repository = $this->createStub(TaskRepository::class);
        $repository->method('findOpenInProject')->willReturn([$alreadyAlex, $free]);
        $dispatcher = new RecordingDispatcher();

        $count = new TaskBulkAssigner($repository, $this->createStub(EntityManagerInterface::class), $dispatcher, new IdentityTranslator())
            ->assign($project, [$alex, $sam]);

        self::assertSame(2, $count);
        self::assertSame([$alex, $sam], $alreadyAlex->getAssignees()->getValues());
        self::assertSame([$alex, $sam], $free->getAssignees()->getValues());
        self::assertSame([ActivityAction::TASKS_BULK_ASSIGNED, ActivityAction::TASKS_BULK_ASSIGNED], $dispatcher->actions());
        self::assertSame(['count' => '2', 'scope' => 'bulk_assign.all_columns'], $dispatcher->last()->payload);
    }

    public function testNothingToDoLogsNothing(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $project = new Project('Projet', $owner);
        $task = new Task($project->addColumn('À faire'), 'Tâche', 0, $owner);
        $task->assign($owner);
        $repository = $this->createStub(TaskRepository::class);
        $repository->method('findOpenInProject')->willReturn([$task]);
        $dispatcher = new RecordingDispatcher();

        $count = new TaskBulkAssigner($repository, $this->createStub(EntityManagerInterface::class), $dispatcher, new IdentityTranslator())
            ->assign($project, [$owner]);

        self::assertSame(0, $count);
        self::assertSame([], $dispatcher->actions());
    }
}
