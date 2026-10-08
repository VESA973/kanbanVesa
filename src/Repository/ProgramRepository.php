<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Program;
use App\Entity\User;
use App\Enum\ProjectRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Program>
 */
class ProgramRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Program::class);
    }

    /**
     * @return list<Program>
     */
    public function findForMember(User $user): array
    {
        /** @var list<Program> */
        return $this->createQueryBuilder('g')
            ->innerJoin('g.members', 'me', 'WITH', 'me.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Programs in which the user may create projects (owner or editor), for the project form.
     */
    public function queryWhereUserCreatesProjects(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('g')
            ->innerJoin('g.members', 'me', 'WITH', 'me.user = :user AND me.role IN (:roles)')
            ->setParameter('user', $user)
            ->setParameter('roles', [ProjectRole::OWNER, ProjectRole::EDITOR])
            ->orderBy('g.name', 'ASC');
    }

    /**
     * The oldest program the user owns: where their projects go when they pick none.
     */
    public function findDefaultFor(User $user): ?Program
    {
        return $this->findOneBy(['owner' => $user], ['id' => 'ASC']);
    }

    public function save(Program $program): void
    {
        $this->getEntityManager()->persist($program);
        $this->getEntityManager()->flush();
    }

    public function remove(Program $program): void
    {
        $this->getEntityManager()->remove($program);
        $this->getEntityManager()->flush();
    }
}
