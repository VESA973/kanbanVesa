<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Invitation;
use App\Entity\Project;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Delegates to the voter of what the invitation targets (a project or a program).
 *
 * @extends Voter<string, Invitation>
 */
final class InvitationVoter extends Voter
{
    /** Seeing the target: others get a 404. */
    public const string VIEW = 'INVITATION_VIEW';
    public const string MANAGE = 'INVITATION_MANAGE';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::MANAGE], true)
            && $subject instanceof Invitation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $target = $subject->getTarget();
        $delegated = match ($attribute) {
            self::VIEW => $target instanceof Project ? ProjectVoter::VIEW : ProgramVoter::VIEW,
            default => $target instanceof Project ? ProjectVoter::MANAGE_MEMBERS : ProgramVoter::MANAGE_MEMBERS,
        };

        return $this->accessDecisionManager->decide($token, [$delegated], $target);
    }
}
