<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Program;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Active projects the user belongs to, with all members preloaded
     * so the list can show avatars and roles without extra queries.
     *
     * @return list<Project>
     */
    public function findActiveForMember(User $user): array
    {
        /** @var list<Project> */
        return $this->createQueryBuilder('p')
            ->innerJoin('p.members', 'me', 'WITH', 'me.user = :user')
            ->innerJoin('p.program', 'g')
            ->leftJoin('p.members', 'm')
            ->leftJoin('m.user', 'u')
            ->addSelect('g', 'm', 'u')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Active projects of a program by name, with their members (for the cards).
     *
     * @return list<Project>
     */
    public function findActiveInProgram(Program $program): array
    {
        /** @var list<Project> */
        return $this->createQueryBuilder('p')
            ->leftJoin('p.members', 'm')
            ->leftJoin('m.user', 'u')
            ->addSelect('m', 'u')
            ->andWhere('p.program = :program')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('program', $program)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Project>
     */
    public function findArchivedForMember(User $user): array
    {
        /** @var list<Project> */
        return $this->createQueryBuilder('p')
            ->innerJoin('p.members', 'me', 'WITH', 'me.user = :user')
            ->andWhere('p.archivedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('p.archivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Project>
     */
    public function search(User $user, string $term, int $limit = 10): array
    {
        /** @var list<Project> */
        return $this->createQueryBuilder('p')
            ->innerJoin('p.members', 'me', 'WITH', 'me.user = :user')
            ->andWhere('p.name LIKE :term OR p.description LIKE :term')
            ->setParameter('user', $user)
            ->setParameter('term', '%'.addcslashes($term, '%_\\').'%')
            ->orderBy('p.archivedAt', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(Project $project): void
    {
        $this->getEntityManager()->persist($project);
        $this->getEntityManager()->flush();
    }

    public function remove(Project $project): void
    {
        $this->getEntityManager()->remove($project);
        $this->getEntityManager()->flush();
    }
}
