<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BoardColumn;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TaskMover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int the confirmed position of the task in $target
     *
     * @throws \InvalidArgumentException when $target belongs to another project
     */
    public function move(Task $task, BoardColumn $target, int $position): int
    {
        if ($target->getProject() !== $task->getProject()) {
            throw new \InvalidArgumentException('A task cannot be moved to another project.');
        }

        $source = $task->getColumn();
        if ($source !== $target) {
            $source->getTasks()->removeElement($task);
            PositionList::remove($source->getTasks(), $task);
        }

        $position = PositionList::insert($target->getTasks(), $task, $position);
        $task->moveTo($target, $position);
        if (!$target->getTasks()->contains($task)) {
            $target->getTasks()->add($task);
        }

        $this->entityManager->flush();

        return $position;
    }

    public function moveToEnd(Task $task, BoardColumn $target): int
    {
        return $this->move($task, $target, \PHP_INT_MAX);
    }
}
