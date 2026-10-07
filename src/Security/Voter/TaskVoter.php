<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\ProjectRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Task>
 */
final class TaskVoter extends Voter
{
    public const string VIEW = 'TASK_VIEW';
    /** Edit, move or delete the task. */
    public const string EDIT = 'TASK_EDIT';
    /** Every member can take part in the discussion. */
    public const string COMMENT = 'TASK_COMMENT';
    /** Mark as completed or reopen (and tick its checklist): anyone who can edit, plus a viewer on a task assigned to them. */
    public const string COMPLETE = 'TASK_COMPLETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::COMPLETE, self::COMMENT], true)
            && $subject instanceof Task;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $role = $subject->getProject()->getRoleOf($user);
        if (null === $role) {
            $vote?->addReason('The user is not a member of the project of this task.');

            return false;
        }

        return match ($attribute) {
            self::VIEW, self::COMMENT => true,
            self::EDIT => ProjectRole::VIEWER !== $role,
            self::COMPLETE => ProjectRole::VIEWER !== $role || $subject->isAssignedTo($user),
            default => false,
        };
    }
}
