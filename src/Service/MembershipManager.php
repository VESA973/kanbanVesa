<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProjectMember;
use App\Enum\ProjectRole;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MembershipManager
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function changeRole(ProjectMember $member, ProjectRole $role): void
    {
        $member->changeRole($role);
        $this->entityManager->flush();
    }

    /**
     * A removed member keeps no task assigned in the project.
     */
    public function remove(ProjectMember $member): void
    {
        $project = $member->getProject();
        $project->removeMember($member);
        $this->taskRepository->unassignInProject($project, $member->getUser());
        $this->entityManager->flush();
    }
}
