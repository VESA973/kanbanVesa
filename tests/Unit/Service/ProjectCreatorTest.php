<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Enum\ProjectColor;
use App\Enum\ProjectRole;
use App\Form\Data\ProjectData;
use App\Repository\ProgramRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Service\ProgramAccess;
use App\Service\ProgramManager;
use App\Service\ProjectCreator;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProjectCreatorTest extends TestCase
{
    public function testCreatesTheProjectWithItsOwnerAsMemberAndDefaultColumns(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())->method('save')->with($this->isInstanceOf(Project::class));

        $data = new ProjectData();
        $data->name = 'Refonte du site';
        $data->description = '   ';
        $data->color = ProjectColor::EMERALD;

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $programManager = new ProgramManager($this->createStub(ProgramRepository::class), $translator);
        $programAccess = new ProgramAccess($this->createStub(TaskRepository::class));
        $project = new ProjectCreator($repository, $translator, $dispatcher = new RecordingDispatcher(), $programManager, $programAccess)->create($data, $owner);

        self::assertSame('Refonte du site', $project->getName());
        self::assertNull($project->getDescription(), 'A blank description is stored as null.');
        self::assertSame(ProjectColor::EMERALD, $project->getColor());
        self::assertSame($owner, $project->getOwner());
        self::assertSame(ProjectRole::OWNER, $project->getRoleOf($owner));
        self::assertCount(1, $project->getMembers());
        self::assertSame([ActivityAction::PROJECT_CREATED], $dispatcher->actions());
        self::assertSame('program.default_name', $project->getProgram()->getName(), 'Without a choice, the project goes to a new default program of its owner.');
        self::assertSame($owner, $project->getProgram()->getOwner());
        self::assertSame(
            ['column.default.todo', 'column.default.in_progress', 'column.default.done'],
            $project->getColumns()->map(static fn ($column): string => $column->getName())->getValues(),
        );
    }
}
