<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
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
}
