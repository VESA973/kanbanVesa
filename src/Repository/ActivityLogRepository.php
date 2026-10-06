<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public const int PAGE_SIZE = 30;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Newest first; one extra row is fetched to know whether a next page exists.
     *
     * @return array{entries: list<ActivityLog>, hasNextPage: bool}
     */
    public function findPage(Project $project, int $page): array
    {
        /** @var list<ActivityLog> $entries */
        $entries = $this->createQueryBuilder('l')
            ->leftJoin('l.user', 'u')
            ->addSelect('u')
            ->andWhere('l.project = :project')
            ->setParameter('project', $project)
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE + 1)
            ->getQuery()
            ->getResult();

        return [
            'entries' => \array_slice($entries, 0, self::PAGE_SIZE),
            'hasNextPage' => \count($entries) > self::PAGE_SIZE,
        ];
    }
}
