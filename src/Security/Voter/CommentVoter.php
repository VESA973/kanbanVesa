<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Comment;
use App\Entity\User;
use App\Enum\ProjectRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Comment>
 */
final class CommentVoter extends Voter
{
    /** The author, or the project owner for moderation. */
    public const string DELETE = 'COMMENT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DELETE === $attribute && $subject instanceof Comment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $project = $subject->getTask()->getProject();
        $role = $project->getRoleOf($user);
        if (null === $role || $project->isArchived()) {
            return false;
        }

        return ProjectRole::OWNER === $role || $subject->isWrittenBy($user);
    }
}
