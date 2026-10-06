<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Repository\TaskRepository;
use App\Service\MembershipManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MembershipManagerTest extends TestCase
{
    public function testChangesTheRoleOfAMember(): void
    {
        $project = new Project('Projet', new User('owner@example.com', 'Olivia', 'Owner'));
        $member = $project->addMember(new User('alex@example.com', 'Alex', 'Martin'), ProjectRole::VIEWER);

        $this->manager()->changeRole($member, ProjectRole::EDITOR);

        self::assertSame(ProjectRole::EDITOR, $member->getRole());
    }

    public function testRemovesTheMemberAndUnassignsTheirTasks(): void
    {
        $project = new Project('Projet', new User('owner@example.com', 'Olivia', 'Owner'));
        $alex = new User('alex@example.com', 'Alex', 'Martin');
        $member = $project->addMember($alex, ProjectRole::EDITOR);
        $tasks = $this->createMock(TaskRepository::class);
        $tasks->expects($this->once())->method('unassignInProject')->with($project, $alex);

        $this->manager($tasks)->remove($member);

        self::assertNull($project->getRoleOf($alex));
    }

    public function testTheOwnerCanNeitherBeRemovedNorDemoted(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $ownerMember = new Project('Projet', $owner)->getMemberOf($owner) ?? throw new \LogicException();

        try {
            $this->manager()->remove($ownerMember);
            self::fail('The owner must not be removable.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $this->manager()->changeRole($ownerMember, ProjectRole::VIEWER);
    }

    public function testNobodyBecomesOwnerThroughARoleChange(): void
    {
        $project = new Project('Projet', new User('owner@example.com', 'Olivia', 'Owner'));
        $member = $project->addMember(new User('alex@example.com', 'Alex', 'Martin'), ProjectRole::EDITOR);

        $this->expectException(\LogicException::class);

        $this->manager()->changeRole($member, ProjectRole::OWNER);
    }

    private function manager(?TaskRepository $tasks = null): MembershipManager
    {
        return new MembershipManager($tasks ?? $this->createStub(TaskRepository::class), $this->createStub(EntityManagerInterface::class));
    }
}
