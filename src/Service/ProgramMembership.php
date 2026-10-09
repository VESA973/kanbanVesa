<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Program;
use App\Entity\ProgramMember;
use App\Entity\User;
use App\Enum\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Members of a program; every change is mirrored on its projects by ProgramAccess.
 */
final readonly class ProgramMembership
{
    public function __construct(
        private ProgramAccess $programAccess,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Joining a program the user already belongs to keeps their current role.
     */
    public function join(Program $program, User $user, ProjectRole $role): ProgramMember
    {
        $member = $program->getMemberOf($user) ?? $program->addMember($user, $role);
        $this->programAccess->grant($member);
        $this->entityManager->flush();

        return $member;
    }

    public function changeRole(ProgramMember $member, ProjectRole $role): void
    {
        $member->changeRole($role);
        $this->programAccess->grant($member);
        $this->entityManager->flush();
    }

    public function toggleLead(ProgramMember $member): void
    {
        $member->toggleLead();
        $this->entityManager->flush();
    }

    public function remove(ProgramMember $member): void
    {
        $program = $member->getProgram();
        $program->removeMember($member);
        $this->programAccess->revoke($program, $member->getUser());
        $this->entityManager->flush();
    }
}
