<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Account actions of the administration area (permissions: UserAdminVoter).
 */
final readonly class UserAdministrator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function verify(User $user): void
    {
        $user->markAsVerified();
        $this->entityManager->flush();
    }

    public function toggleActive(User $user): void
    {
        $user->isActive() ? $user->deactivate() : $user->activate();
        $this->entityManager->flush();
    }

    public function toggleAdmin(User $user): void
    {
        $user->isAdmin() ? $user->demoteFromAdmin() : $user->promoteToAdmin();
        $this->entityManager->flush();
    }

    /**
     * The database does the rest: memberships, comments and pending invitations go
     * with the account; tasks stay, unassigned and with an anonymous author.
     */
    public function delete(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
