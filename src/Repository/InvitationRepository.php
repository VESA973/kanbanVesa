<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invitation;
use App\Entity\Project;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findOneByPlainToken(string $plainToken): ?Invitation
    {
        return $this->findOneBy(['tokenHash' => Invitation::hashToken($plainToken)]);
    }

    public function findNotAcceptedFor(Project $project, string $email): ?Invitation
    {
        return $this->findOneBy(['project' => $project, 'email' => mb_strtolower(trim($email)), 'acceptedAt' => null]);
    }

    /**
     * @return list<Invitation>
     */
    public function findPendingFor(Project $project): array
    {
        /** @var list<Invitation> */
        return $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('project', $project)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Invitation>
     */
    public function findPendingForTask(Task $task): array
    {
        /** @var list<Invitation> */
        return $this->createQueryBuilder('i')
            ->andWhere('i.task = :task')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('task', $task)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    public function save(Invitation $invitation): void
    {
        $this->getEntityManager()->persist($invitation);
        $this->getEntityManager()->flush();
    }

    public function remove(Invitation $invitation): void
    {
        $this->getEntityManager()->remove($invitation);
        $this->getEntityManager()->flush();
    }
}
