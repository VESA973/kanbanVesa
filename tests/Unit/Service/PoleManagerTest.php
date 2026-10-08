<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Pole;
use App\Entity\Program;
use App\Entity\User;
use App\Repository\PoleRepository;
use App\Security\Voter\PoleVoter;
use App\Service\PoleManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PoleManagerTest extends TestCase
{
    private User $user;
    private Pole $evangelism;
    private Pole $social;

    protected function setUp(): void
    {
        $this->user = new User('camille@example.com', 'Camille', 'Martin');
        $this->evangelism = new Pole($this->user, 'Évangélisation', 0);
        $this->social = new Pole($this->user, 'Social', 1);
    }

    public function testAProgramIsFiledInOnlyOneOfTheUsersPoles(): void
    {
        $program = new Program('Mairie 2027', $this->user);
        $manager = $this->manager();

        $manager->classify($program, $this->user, $this->evangelism);
        $manager->classify($program, $this->user, $this->social);

        self::assertFalse($this->evangelism->contains($program));
        self::assertTrue($this->social->contains($program));
        self::assertSame($this->social, $manager->poleOf($program, $this->user));

        $manager->classify($program, $this->user, null);
        self::assertNull($manager->poleOf($program, $this->user));
    }

    public function testAProgramCannotBeFiledInSomeoneElsesPole(): void
    {
        $other = new User('other@example.com', 'Olivia', 'Other');

        $this->expectException(\InvalidArgumentException::class);

        $this->manager()->classify(new Program('Mairie 2027', $other), $other, $this->social);
    }

    public function testOnlyTheOwnerManagesAPole(): void
    {
        $voter = new PoleVoter();
        $other = new User('other@example.com', 'Olivia', 'Other');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new UsernamePasswordToken($this->user, 'main'), $this->social, [PoleVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new UsernamePasswordToken($other, 'main'), $this->social, [PoleVoter::MANAGE]));
    }

    private function manager(): PoleManager
    {
        $repository = $this->createStub(PoleRepository::class);
        $repository->method('findForUser')->willReturn([$this->evangelism, $this->social]);

        return new PoleManager($repository, $this->createStub(EntityManagerInterface::class));
    }
}
