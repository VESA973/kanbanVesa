<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Project>
 */
final class ProjectVoter extends Voter
{
    public const string VIEW = 'PROJECT_VIEW';
    public const string EDIT = 'PROJECT_EDIT';
    public const string DELETE = 'PROJECT_DELETE';
    public const string MANAGE_COLUMNS = 'PROJECT_MANAGE_COLUMNS';
    public const string CREATE_TASK = 'PROJECT_CREATE_TASK';
    public const string MANAGE_MEMBERS = 'PROJECT_MANAGE_MEMBERS';

    private const array ATTRIBUTES = [self::VIEW, self::EDIT, self::DELETE, self::MANAGE_COLUMNS, self::CREATE_TASK, self::MANAGE_MEMBERS];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, true)
            && $subject instanceof Project;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $role = $subject->getRoleOf($user);
        if (null === $role) {
            $vote?->addReason('The user is not a member of this project.');

            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::EDIT, self::DELETE, self::MANAGE_MEMBERS => ProjectRole::OWNER === $role,
            self::MANAGE_COLUMNS, self::CREATE_TASK => ProjectRole::VIEWER !== $role,
            default => false,
        };
    }
}
