<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Program;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Security\Voter\ProgramVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ProgramVoterTest extends TestCase
{
    private const int GRANTED = VoterInterface::ACCESS_GRANTED;
    private const int DENIED = VoterInterface::ACCESS_DENIED;

    /**
     * @return iterable<string, array{?ProjectRole, string, int}>
     */
    public static function permissionMatrix(): iterable
    {
        $expected = [
            ProgramVoter::VIEW => ['owner' => self::GRANTED, 'editor' => self::GRANTED, 'viewer' => self::GRANTED, 'none' => self::DENIED],
            ProgramVoter::CREATE_PROJECT => ['owner' => self::GRANTED, 'editor' => self::GRANTED, 'viewer' => self::DENIED, 'none' => self::DENIED],
            ProgramVoter::EDIT => ['owner' => self::GRANTED, 'editor' => self::DENIED, 'viewer' => self::DENIED, 'none' => self::DENIED],
            ProgramVoter::DELETE => ['owner' => self::GRANTED, 'editor' => self::DENIED, 'viewer' => self::DENIED, 'none' => self::DENIED],
            ProgramVoter::MANAGE_MEMBERS => ['owner' => self::GRANTED, 'editor' => self::DENIED, 'viewer' => self::DENIED, 'none' => self::DENIED],
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
        $program = new Program('Mairie 2027', $owner);
        $user = match ($role) {
            ProjectRole::OWNER => $owner,
            null => new User('stranger@example.com', 'Sam', 'Stranger'),
            default => new User('member@example.com', 'Max', 'Member'),
        };
        if (null !== $role && ProjectRole::OWNER !== $role) {
            $program->addMember($user, $role);
        }

        $vote = new ProgramVoter()->vote(new UsernamePasswordToken($user, 'main', $user->getRoles()), $program, [$attribute]);

        self::assertSame($expected, $vote);
    }

    public function testAnonymousIsDenied(): void
    {
        $program = new Program('Mairie 2027', new User('owner@example.com', 'Olivia', 'Owner'));

        self::assertSame(self::DENIED, new ProgramVoter()->vote(new NullToken(), $program, [ProgramVoter::VIEW]));
    }
}
