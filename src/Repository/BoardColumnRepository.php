<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BoardColumn;
use App\Entity\Project;
use App\Service\PositionList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BoardColumn>
 */
class BoardColumnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoardColumn::class);
    }

    /**
     * The whole board in one query: columns, their tasks and assignees, in display order.
     *
     * @return list<BoardColumn>
     */
    public function findBoard(Project $project): array
    {
        /** @var list<BoardColumn> */
        return $this->createQueryBuilder('c')
            ->leftJoin('c.tasks', 't')
            ->leftJoin('t.assignees', 'a')
            ->addSelect('t', 'a')
            ->andWhere('c.project = :project')
            ->setParameter('project', $project)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(BoardColumn $column): void
    {
        $this->getEntityManager()->persist($column);
        $this->getEntityManager()->flush();
    }

    public function remove(BoardColumn $column): void
    {
        $project = $column->getProject();
        $project->getColumns()->removeElement($column);
        PositionList::remove($project->getColumns(), $column);
        $this->getEntityManager()->remove($column);
        $this->getEntityManager()->flush();
    }
}
