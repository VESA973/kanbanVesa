<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Program;
use App\Entity\ProgramMember;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Repository\TaskRepository;

/**
 * Mirrors the members of a program on each of its projects as "inherited" project
 * members, so every permission check and query keeps relying on ProjectMember only.
 * A direct membership (invited on the project itself) is never touched.
 * Callers flush.
 */
final readonly class ProgramAccess
{
    public function __construct(
        private TaskRepository $taskRepository,
    ) {
    }

    /**
     * For a project just created in (or moved to) a program.
     */
    public function shareWithProgramMembers(Project $project): void
    {
        foreach ($project->getProgram()->getMembers() as $member) {
            $this->grantOn($project, $member->getUser(), $member->getRole());
        }
    }

    /**
     * For a program member just added, or whose role changed.
     */
    public function grant(ProgramMember $member): void
    {
        foreach ($member->getProgram()->getProjects() as $project) {
            $this->grantOn($project, $member->getUser(), $member->getRole());
        }
    }

    /**
     * For a member leaving the program: they lose the inherited access and their tasks there.
     */
    public function revoke(Program $program, User $user): void
    {
        foreach ($program->getProjects() as $project) {
            $member = $project->getMemberOf($user);
            if (null === $member || !$member->isInherited()) {
                continue;
            }

            $project->removeMember($member);
            $this->taskRepository->unassignInProject($project, $user);
        }
    }

    private function grantOn(Project $project, User $user, ProjectRole $role): void
    {
        $member = $project->getMemberOf($user);
        if (null === $member) {
            $project->addMember($user, $role, inherited: true);

            return;
        }

        if ($member->isInherited() && $member->getRole() !== $role) {
            $member->changeRole($role);
        }
    }
}
