<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Security\Voter\ProjectVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ProjectVoterTest extends TestCase
{
    private const int GRANTED = VoterInterface::ACCESS_GRANTED;
    private const int DENIED = VoterInterface::ACCESS_DENIED;

    /**
     * @return iterable<string, array{?ProjectRole, string, int}>
     */
    public static function permissionMatrix(): iterable
    {
        $expected = [
            ProjectVoter::VIEW => ['owner' => self::GRANTED, 'editor' => self::GRANTED, 'viewer' => self::GRANTED, 'none' => self::DENIED],
            ProjectVoter::EDIT => ['owner' => self::GRANTED, 'editor' => self::DENIED, 'viewer' => self::DENIED, 'none' => self::DENIED],
            ProjectVoter::DELETE => ['owner' => self::GRANTED, 'editor' => self::DENIED, 'viewer' => self::DENIED, 'none' => self::DENIED],
            ProjectVoter::MANAGE_COLUMNS => ['owner' => self::GRANTED, 'editor' => self::GRANTED, 'viewer' => self::DENIED, 'none' => self::DENIED],
            ProjectVoter::MANAGE_MEMBERS => ['owner' => self::GRANTED, 'editor' => self::DENIED, 'viewer' => self::DENIED, 'none' => self::DENIED],
            ProjectVoter::TRACK => ['owner' => self::GRANTED, 'editor' => self::DENIED, 'viewer' => self::DENIED, 'none' => self::DENIED],
            ProjectVoter::MANAGE_LABELS => ['owner' => self::GRANTED, 'editor' => self::GRANTED, 'viewer' => self::DENIED, 'none' => self::DENIED],
            ProjectVoter::CREATE_TASK => ['owner' => self::GRANTED, 'editor' => self::GRANTED, 'viewer' => self::DENIED, 'none' => self::DENIED],
        ];

        foreach ($expected as $attribute => $results) {
            foreach ($results as $role => $result) {
                yield $attribute.' as '.$role => [ProjectRole::tryFrom($role), $attribute, $result];
            }
        }
    }

    #[DataProvider('permissionMatrix')]
    public function testPermissionMatrix(?ProjectRole $role, string $attribute, int $expected): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $project = new Project('Projet', $owner);
        $user = match ($role) {
            ProjectRole::OWNER => $owner,
            null => new User('stranger@example.com', 'Sam', 'Stranger'),
            default => $this->member($project, $role),
        };

        $vote = new ProjectVoter()->vote(new UsernamePasswordToken($user, 'main', $user->getRoles()), $project, [$attribute]);

        self::assertSame($expected, $vote);
    }

    public function testAnonymousUserIsDenied(): void
    {
        $project = new Project('Projet', new User('owner@example.com', 'Olivia', 'Owner'));

        self::assertSame(self::DENIED, new ProjectVoter()->vote(new NullToken(), $project, [ProjectVoter::VIEW]));
    }

    public function testAbstainsOnUnsupportedAttributeOrSubject(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $token = new UsernamePasswordToken($owner, 'main', $owner->getRoles());
        $voter = new ProjectVoter();

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new Project('Projet', $owner), ['UNKNOWN']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new \stdClass(), [ProjectVoter::VIEW]));
    }

    private function member(Project $project, ProjectRole $role): User
    {
        $user = new User($role->value.'@example.com', 'Membre', ucfirst($role->value));
        $project->addMember($user, $role);

        return $user;
    }
}
