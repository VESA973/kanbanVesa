<?php

declare(strict_types=1);

namespace App\Repository;

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
            ->leftJoin('p.members', 'm')
            ->leftJoin('m.user', 'u')
            ->addSelect('m', 'u')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
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
