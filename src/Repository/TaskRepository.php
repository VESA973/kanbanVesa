<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Service\PositionList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function save(Task $task): void
    {
        $this->getEntityManager()->persist($task);
        $this->getEntityManager()->flush();
    }

    public function remove(Task $task): void
    {
        $column = $task->getColumn();
        $column->getTasks()->removeElement($task);
        PositionList::remove($column->getTasks(), $task);
        $this->getEntityManager()->remove($task);
        $this->getEntityManager()->flush();
    }

    public function unassignInProject(Project $project, User $user): void
    {
        $tasks = $this->createQueryBuilder('t')
            ->innerJoin('t.column', 'c')
            ->andWhere('c.project = :project')
            ->andWhere('t.assignee = :user')
            ->setParameter('project', $project)
            ->setParameter('user', $user)
            ->getQuery()
            ->toIterable();

        foreach ($tasks as $task) {
            $task->unassign();
        }
    }

    /**
     * Tasks assigned to the user in the active projects they belong to.
     *
     * @return list<Task>
     */
    public function findAssignedTo(User $user): array
    {
        /** @var list<Task> */
        return $this->createQueryBuilder('t')
            ->innerJoin('t.column', 'c')
            ->innerJoin('c.project', 'p')
            ->innerJoin('p.members', 'm', 'WITH', 'm.user = :user')
            ->addSelect('c', 'p')
            ->andWhere('t.assignee = :user')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('t.dueDate', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Counters per assignee id (0 for unassigned tasks), computed in a single query.
     *
     * @return array<int, array{total: int, completed: int, overdue: int}>
     */
    public function countByAssignee(Project $project, \DateTimeImmutable $now): array
    {
        /** @var list<array{assigneeId: int|string|null, total: int|string, completed: int|string|null, overdue: int|string|null}> $rows */
        $rows = $this->createQueryBuilder('t')
            ->select('IDENTITY(t.assignee) AS assigneeId')
            ->addSelect('COUNT(t.id) AS total')
            ->addSelect('SUM(CASE WHEN t.completedAt IS NOT NULL THEN 1 ELSE 0 END) AS completed')
            ->addSelect('SUM(CASE WHEN t.completedAt IS NULL AND t.dueDate < :today THEN 1 ELSE 0 END) AS overdue')
            ->innerJoin('t.column', 'c')
            ->andWhere('c.project = :project')
            ->setParameter('project', $project)
            ->setParameter('today', $now->setTime(0, 0), 'date_immutable')
            ->groupBy('t.assignee')
            ->getQuery()
            ->getArrayResult();

        $counters = [];
        foreach ($rows as $row) {
            $counters[(int) $row['assigneeId']] = [
                'total' => (int) $row['total'],
                'completed' => (int) $row['completed'],
                'overdue' => (int) $row['overdue'],
            ];
        }

        return $counters;
    }

    /**
     * @return list<Task>
     */
    public function findOverdue(Project $project, \DateTimeImmutable $now): array
    {
        /** @var list<Task> */
        return $this->createQueryBuilder('t')
            ->innerJoin('t.column', 'c')
            ->leftJoin('t.assignee', 'a')
            ->addSelect('c', 'a')
            ->andWhere('c.project = :project')
            ->andWhere('t.completedAt IS NULL')
            ->andWhere('t.dueDate < :today')
            ->setParameter('project', $project)
            ->setParameter('today', $now->setTime(0, 0), 'date_immutable')
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
