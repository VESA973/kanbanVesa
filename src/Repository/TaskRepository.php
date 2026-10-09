<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Model\Progress;
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
     * Tasks whose title or description contains $term, in the projects the user belongs to.
     * The utf8mb4_unicode_ci collation makes LIKE case and accent insensitive.
     *
     * @return list<Task>
     */
    public function search(User $user, string $term, int $limit = 30): array
    {
        /** @var list<Task> */
        return $this->createQueryBuilder('t')
            ->innerJoin('t.column', 'c')
            ->innerJoin('c.project', 'p')
            ->innerJoin('p.members', 'm', 'WITH', 'm.user = :user')
            ->addSelect('c', 'p')
            ->andWhere('t.title LIKE :term OR t.description LIKE :term')
            ->setParameter('user', $user)
            ->setParameter('term', '%'.addcslashes($term, '%_\\').'%')
            ->orderBy('p.archivedAt', 'ASC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
            ->innerJoin('p.program', 'g')
            ->innerJoin('p.members', 'm', 'WITH', 'm.user = :user')
            ->addSelect('c', 'p', 'g')
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
     * Completed / total tasks per project id, in a single query. Projects without tasks are absent.
     *
     * @param list<Project> $projects
     *
     * @return array<int, Progress>
     */
    public function countByProject(array $projects): array
    {
        if ([] === $projects) {
            return [];
        }

        /** @var list<array{projectId: int|string, total: int|string, completed: int|string|null}> $rows */
        $rows = $this->createQueryBuilder('t')
            ->select('IDENTITY(c.project) AS projectId')
            ->addSelect('COUNT(t.id) AS total')
            ->addSelect('SUM(CASE WHEN t.completedAt IS NOT NULL THEN 1 ELSE 0 END) AS completed')
            ->innerJoin('t.column', 'c')
            ->andWhere('c.project IN (:projects)')
            ->setParameter('projects', $projects)
            ->groupBy('c.project')
            ->getQuery()
            ->getArrayResult();

        $counters = [];
        foreach ($rows as $row) {
            $counters[(int) $row['projectId']] = new Progress((int) $row['total'], (int) $row['completed']);
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

    /**
     * @return list<Task>
     */
    public function findNeedingDueReminder(\DateTimeImmutable $dueDate): array
    {
        /** @var list<Task> */
        return $this->createQueryBuilder('t')
            ->innerJoin('t.assignee', 'a')
            ->innerJoin('t.column', 'c')
            ->innerJoin('c.project', 'p')
            ->addSelect('a', 'c', 'p')
            ->andWhere('t.dueDate = :dueDate')
            ->andWhere('t.completedAt IS NULL')
            ->andWhere('t.dueReminderSentAt IS NULL')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('dueDate', $dueDate->setTime(0, 0), 'date_immutable')
            ->getQuery()
            ->getResult();
    }

    /**
     * Loads labels and checklist items of the board cards in two queries
     * instead of one per card.
     *
     * @param list<Task> $tasks
     */
    public function preloadCardDetails(array $tasks): void
    {
        if ([] === $tasks) {
            return;
        }

        foreach (['labels', 'checklistItems'] as $collection) {
            $this->createQueryBuilder('t')
                ->leftJoin('t.'.$collection, 'x')
                ->addSelect('x')
                ->andWhere('t IN (:tasks)')
                ->setParameter('tasks', $tasks)
                ->getQuery()
                ->getResult();
        }
    }

    /**
     * @param list<Task> $tasks
     *
     * @return array<int, int> number of comments per task id
     */
    public function countCommentsByTask(array $tasks): array
    {
        if ([] === $tasks) {
            return [];
        }

        /** @var list<array{id: int|string, comments: int|string}> $rows */
        $rows = $this->createQueryBuilder('t')
            ->select('t.id AS id', 'COUNT(cm.id) AS comments')
            ->innerJoin('t.comments', 'cm')
            ->andWhere('t IN (:tasks)')
            ->setParameter('tasks', $tasks)
            ->groupBy('t.id')
            ->getQuery()
            ->getArrayResult();

        return array_combine(array_map(intval(...), array_column($rows, 'id')), array_map(intval(...), array_column($rows, 'comments')));
    }

    /**
     * @return list<array{assigneeId: ?int, completedAt: \DateTimeImmutable}>
     */
    public function findCompletionsSince(Project $project, \DateTimeImmutable $since): array
    {
        /** @var list<array{assigneeId: int|string|null, completedAt: \DateTimeImmutable}> $rows */
        $rows = $this->createQueryBuilder('t')
            ->select('IDENTITY(t.assignee) AS assigneeId', 't.completedAt AS completedAt')
            ->innerJoin('t.column', 'c')
            ->andWhere('c.project = :project')
            ->andWhere('t.completedAt >= :since')
            ->setParameter('project', $project)
            ->setParameter('since', $since)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'assigneeId' => null === $row['assigneeId'] ? null : (int) $row['assigneeId'],
            'completedAt' => $row['completedAt'],
        ], $rows);
    }
}
