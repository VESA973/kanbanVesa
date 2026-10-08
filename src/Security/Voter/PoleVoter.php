<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Pole;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Poles are personal: only their owner sees or changes them (others get a 404).
 *
 * @extends Voter<string, Pole>
 */
final class PoleVoter extends Voter
{
    public const string MANAGE = 'POLE_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof Pole;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if ($user instanceof User && $subject->isOwnedBy($user)) {
            return true;
        }
        $vote?->addReason('Poles are personal to their owner.');

        return false;
    }
}
