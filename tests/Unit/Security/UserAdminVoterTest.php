<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Security\Voter\UserAdminVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class UserAdminVoterTest extends TestCase
{
    private const int GRANTED = VoterInterface::ACCESS_GRANTED;
    private const int DENIED = VoterInterface::ACCESS_DENIED;

    public function testAdminManagesOtherAccounts(): void
    {
        $admin = $this->admin();
        $user = $this->user(2);

        foreach ([UserAdminVoter::VERIFY, UserAdminVoter::TOGGLE_ACTIVE, UserAdminVoter::TOGGLE_ADMIN, UserAdminVoter::DELETE] as $attribute) {
            self::assertSame(self::GRANTED, $this->vote($admin, $user, $attribute, ownedProjects: 0), $attribute);
        }
    }

    public function testAnAdminNeverActsOnTheirOwnAccount(): void
    {
        $admin = $this->admin();

        foreach ([UserAdminVoter::TOGGLE_ACTIVE, UserAdminVoter::TOGGLE_ADMIN, UserAdminVoter::DELETE] as $attribute) {
            self::assertSame(self::DENIED, $this->vote($admin, $admin, $attribute, ownedProjects: 0), $attribute);
        }
    }

    public function testProjectOwnersCannotBeDeleted(): void
    {
        self::assertSame(self::DENIED, $this->vote($this->admin(), $this->user(2), UserAdminVoter::DELETE, ownedProjects: 1));
    }

    public function testVerifyingIsOnlyOfferedForUnverifiedAccounts(): void
    {
        $user = $this->user(2);
        $user->markAsVerified();

        self::assertSame(self::DENIED, $this->vote($this->admin(), $user, UserAdminVoter::VERIFY, ownedProjects: 0));
    }

    public function testNonAdminIsDeniedEverything(): void
    {
        foreach ([UserAdminVoter::VERIFY, UserAdminVoter::TOGGLE_ACTIVE, UserAdminVoter::TOGGLE_ADMIN, UserAdminVoter::DELETE] as $attribute) {
            self::assertSame(self::DENIED, $this->vote($this->user(1), $this->user(2), $attribute, ownedProjects: 0), $attribute);
        }
    }

    private function vote(User $current, User $subject, string $attribute, int $ownedProjects): int
    {
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('count')->willReturn($ownedProjects);

        return new UserAdminVoter($projects)->vote(new UsernamePasswordToken($current, 'main', $current->getRoles()), $subject, [$attribute]);
    }

    private function admin(): User
    {
        $admin = $this->user(1);
        $admin->promoteToAdmin();

        return $admin;
    }

    private function user(int $id): User
    {
        $user = new User('user'.$id.'@example.com', 'Prénom', 'Nom');
        new \ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
