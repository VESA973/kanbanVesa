<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Program;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\ProgramException;
use App\Repository\ProgramRepository;
use App\Service\ProgramImageStorage;
use App\Service\ProgramManager;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProgramManagerTest extends TestCase
{
    public function testAProgramWithProjectsCannotBeDeleted(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $program = new Program('Mairie 2027', $owner);
        new Project('Voirie', $owner)->placeIn($program);
        $repository = $this->createMock(ProgramRepository::class);
        $repository->expects($this->never())->method('remove');

        $this->expectExceptionObject(ProgramException::notEmpty());

        $this->manager($repository)->delete($program);
    }

    public function testTheDefaultProgramIsTheOldestOwnedOrANewOne(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $existing = new Program('Mes projets', $owner);
        $repository = $this->createStub(ProgramRepository::class);
        $repository->method('findDefaultFor')->willReturn($existing, null);
        $manager = $this->manager($repository);

        self::assertSame($existing, $manager->defaultFor($owner));
        $created = $manager->defaultFor($owner);
        self::assertSame('program.default_name', $created->getName());
        self::assertSame($owner, $created->getOwner());
    }

    private function manager(ProgramRepository $repository): ProgramManager
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ProgramManager($repository, $translator, new ProgramImageStorage(sys_get_temp_dir()));
    }
}
