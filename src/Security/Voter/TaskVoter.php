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

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT], true)
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
            self::VIEW => true,
            self::EDIT => ProjectRole::VIEWER !== $role,
            default => false,
        };
    }
}
