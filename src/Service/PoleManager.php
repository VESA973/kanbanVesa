<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Pole;
use App\Entity\Program;
use App\Entity\User;
use App\Repository\PoleRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Personal poles and the filing of programs in them (a program is in at most one
 * pole of a given user).
 */
final readonly class PoleManager
{
    public function __construct(
        private PoleRepository $poleRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(User $owner, string $name): Pole
    {
        $pole = new Pole($owner, $name, \count($this->poleRepository->findForUser($owner)));
        $this->poleRepository->save($pole);

        return $pole;
    }

    public function rename(Pole $pole, string $name): void
    {
        $pole->rename($name);
        $this->entityManager->flush();
    }

    /**
     * @param -1|1 $step up or down
     */
    public function move(Pole $pole, int $step): void
    {
        PositionList::insert($this->poleRepository->findForUser($pole->getOwner()), $pole, $pole->getPosition() + $step);
        $this->entityManager->flush();
    }

    /**
     * Its programs are simply no longer filed ("Sans pôle"): nothing else is deleted.
     */
    public function delete(Pole $pole): void
    {
        $poles = $this->poleRepository->findForUser($pole->getOwner());
        PositionList::remove($poles, $pole);
        $this->entityManager->remove($pole);
        $this->entityManager->flush();
    }

    /**
     * Files $program in $pole for $user, or in none of their poles when $pole is null.
     *
     * @throws \InvalidArgumentException when $pole belongs to another user
     */
    public function classify(Program $program, User $user, ?Pole $pole): void
    {
        if (null !== $pole && !$pole->isOwnedBy($user)) {
            throw new \InvalidArgumentException('A program can only be filed in a pole of one\'s own.');
        }

        foreach ($this->poleRepository->findForUser($user) as $userPole) {
            $userPole->getPrograms()->removeElement($program);
        }
        $pole?->getPrograms()->add($program);
        $this->entityManager->flush();
    }

    public function poleOf(Program $program, User $user): ?Pole
    {
        foreach ($this->poleRepository->findForUser($user) as $pole) {
            if ($pole->contains($program)) {
                return $pole;
            }
        }

        return null;
    }
}
