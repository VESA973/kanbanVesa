<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Repository\ProjectRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * What an administrator may do to a user account (the subject).
 * Nobody acts on their own account, so an administrator can never lock themselves out.
 *
 * @extends Voter<string, User>
 */
final class UserAdminVoter extends Voter
{
    public const string VERIFY = 'USER_VERIFY';
    public const string TOGGLE_ACTIVE = 'USER_TOGGLE_ACTIVE';
    public const string TOGGLE_ADMIN = 'USER_TOGGLE_ADMIN';
    /** Refused while the user owns projects: they must be deleted first. */
    public const string DELETE = 'USER_DELETE';

    public function __construct(
        private readonly ProjectRepository $projectRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VERIFY, self::TOGGLE_ACTIVE, self::TOGGLE_ADMIN, self::DELETE], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $admin = $token->getUser();
        if (!$admin instanceof User || !$admin->isAdmin()) {
            return false;
        }

        $isSelf = $admin->getId() === $subject->getId();

        return match ($attribute) {
            self::VERIFY => !$subject->isVerified(),
            self::TOGGLE_ACTIVE, self::TOGGLE_ADMIN => !$isSelf,
            self::DELETE => !$isSelf && 0 === $this->projectRepository->count(['owner' => $subject]),
            default => false,
        };
    }
}
