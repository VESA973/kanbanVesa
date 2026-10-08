<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Program;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Program>
 */
final class ProgramVoter extends Voter
{
    /** Program members, and people invited to at least one of its projects (who only see those). */
    public const string VIEW = 'PROGRAM_VIEW';
    /** Members of the program itself: its member list and all its projects. */
    public const string VIEW_MEMBERS = 'PROGRAM_VIEW_MEMBERS';
    public const string CREATE_PROJECT = 'PROGRAM_CREATE_PROJECT';
    public const string EDIT = 'PROGRAM_EDIT';
    public const string DELETE = 'PROGRAM_DELETE';
    public const string MANAGE_MEMBERS = 'PROGRAM_MANAGE_MEMBERS';

    private const array ATTRIBUTES = [self::VIEW, self::VIEW_MEMBERS, self::CREATE_PROJECT, self::EDIT, self::DELETE, self::MANAGE_MEMBERS];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, true)
            && $subject instanceof Program;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $role = $subject->getRoleOf($user);
        if (null === $role) {
            if (self::VIEW === $attribute && $this->belongsToOneOfItsProjects($subject, $user)) {
                return true;
            }
            $vote?->addReason('The user is not a member of this program.');

            return false;
        }

        return match ($attribute) {
            self::VIEW, self::VIEW_MEMBERS => true,
            self::CREATE_PROJECT => ProjectRole::VIEWER !== $role,
            self::EDIT, self::DELETE, self::MANAGE_MEMBERS => ProjectRole::OWNER === $role,
            default => false,
        };
    }

    private function belongsToOneOfItsProjects(Program $program, User $user): bool
    {
        return $program->getProjects()->exists(static fn (int $key, Project $project): bool => null !== $project->getRoleOf($user));
    }
}
