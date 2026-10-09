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
    /** Archive or unarchive. */
    public const string ARCHIVE = 'PROJECT_ARCHIVE';
    public const string MANAGE_COLUMNS = 'PROJECT_MANAGE_COLUMNS';
    public const string CREATE_TASK = 'PROJECT_CREATE_TASK';
    /** Assign members to many tasks at once (bulk assignment). */
    public const string ASSIGN_TASKS = 'PROJECT_ASSIGN_TASKS';
    public const string MANAGE_MEMBERS = 'PROJECT_MANAGE_MEMBERS';
    public const string MANAGE_LABELS = 'PROJECT_MANAGE_LABELS';
    /** Members progress and activity log. */
    public const string TRACK = 'PROJECT_TRACK';

    /** What stays possible on an archived (read-only) project. */
    private const array ARCHIVED_ATTRIBUTES = [self::VIEW, self::TRACK, self::ARCHIVE, self::DELETE];

    private const array ATTRIBUTES = [self::VIEW, self::EDIT, self::DELETE, self::ARCHIVE, self::MANAGE_COLUMNS, self::CREATE_TASK, self::ASSIGN_TASKS, self::MANAGE_MEMBERS, self::TRACK, self::MANAGE_LABELS];

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

        if ($subject->isArchived() && !\in_array($attribute, self::ARCHIVED_ATTRIBUTES, true)) {
            $vote?->addReason('The project is archived (read-only).');

            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::EDIT, self::DELETE, self::ARCHIVE, self::MANAGE_MEMBERS, self::TRACK => ProjectRole::OWNER === $role,
            self::MANAGE_COLUMNS, self::CREATE_TASK, self::ASSIGN_TASKS, self::MANAGE_LABELS => ProjectRole::VIEWER !== $role,
            default => false,
        };
    }
}
