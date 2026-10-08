<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Program;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Repository\TaskRepository;
use App\Service\ProgramAccess;
use PHPUnit\Framework\TestCase;

final class ProgramAccessTest extends TestCase
{
    private User $owner;
    private Program $program;
    private Project $first;
    private Project $second;

    protected function setUp(): void
    {
        $this->owner = new User('owner@example.com', 'Olivia', 'Owner');
        $this->program = new Program('Mairie 2027', $this->owner);
        $this->first = $this->projectInProgram('Voirie');
        $this->second = $this->projectInProgram('Écoles');
    }

    public function testAProgramMemberGetsTheSameRoleOnEveryProject(): void
    {
        $alex = new User('alex@example.com', 'Alex', 'Martin');

        $this->access()->grant($this->program->addMember($alex, ProjectRole::EDITOR));

        foreach ([$this->first, $this->second] as $project) {
            $member = $project->getMemberOf($alex) ?? throw new \LogicException();
            self::assertSame(ProjectRole::EDITOR, $member->getRole());
            self::assertTrue($member->isInherited());
        }
    }

    public function testAProjectCreatedLaterIsSharedWithTheProgramMembers(): void
    {
        $alex = new User('alex@example.com', 'Alex', 'Martin');
        $this->program->addMember($alex, ProjectRole::VIEWER);

        $third = $this->projectInProgram('Culture');
        $this->access()->shareWithProgramMembers($third);

        self::assertSame(ProjectRole::VIEWER, $third->getRoleOf($alex));
    }

    public function testARoleChangeFollowsButADirectMembershipIsNeverTouched(): void
    {
        $alex = new User('alex@example.com', 'Alex', 'Martin');
        $this->first->addMember($alex, ProjectRole::VIEWER);
        $member = $this->program->addMember($alex, ProjectRole::VIEWER);
        $this->access()->grant($member);

        $member->changeRole(ProjectRole::EDITOR);
        $this->access()->grant($member);

        self::assertSame(ProjectRole::VIEWER, $this->first->getRoleOf($alex), 'Invited on the project itself: unchanged.');
        self::assertSame(ProjectRole::EDITOR, $this->second->getRoleOf($alex));
    }

    public function testLeavingTheProgramRemovesOnlyTheInheritedAccess(): void
    {
        $alex = new User('alex@example.com', 'Alex', 'Martin');
        $this->first->addMember($alex, ProjectRole::EDITOR);
        $member = $this->program->addMember($alex, ProjectRole::VIEWER);
        $this->access()->grant($member);

        $this->program->removeMember($member);
        $this->access()->revoke($this->program, $alex);

        self::assertSame(ProjectRole::EDITOR, $this->first->getRoleOf($alex));
        self::assertNull($this->second->getRoleOf($alex));
    }

    public function testTheProgramOwnerOwnsEveryProject(): void
    {
        $colleague = new User('colleague@example.com', 'Chris', 'Colleague');
        $project = new Project('Projet du collègue', $colleague);
        $project->placeIn($this->program);

        $this->access()->shareWithProgramMembers($project);

        self::assertSame(ProjectRole::OWNER, $project->getRoleOf($this->owner));
        self::assertSame(ProjectRole::OWNER, $project->getRoleOf($colleague));
    }

    private function projectInProgram(string $name): Project
    {
        $project = new Project($name, $this->owner);
        $project->placeIn($this->program);

        return $project;
    }

    private function access(): ProgramAccess
    {
        return new ProgramAccess($this->createStub(TaskRepository::class));
    }
}
