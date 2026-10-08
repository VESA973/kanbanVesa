<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BoardColumn;
use App\Entity\Category;
use App\Entity\Task;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class TaskMover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Moves the task to the cell ($target, $category) of the board. Positions are counted within a cell.
     *
     * @return int the confirmed position of the task in the cell
     *
     * @throws \InvalidArgumentException when $target or $category belongs to another project
     */
    public function move(Task $task, BoardColumn $target, int $position, ?Category $category = null): int
    {
        $category ??= $task->getCategory();
        if ($target->getProject() !== $task->getProject() || $category->getProject() !== $task->getProject()) {
            throw new \InvalidArgumentException('A task cannot be moved to another project.');
        }

        $source = $task->getColumn();
        $sourceCategory = $task->getCategory();
        if ($source !== $target || $sourceCategory !== $category) {
            PositionList::remove($source->getTasksIn($sourceCategory), $task);
            $this->logMove($task, $source, $target, $sourceCategory, $category);
        }

        $position = PositionList::insert($target->getTasksIn($category), $task, $position);
        $this->relink($task, $target, $category);
        $task->moveTo($target, $category, $position);

        $this->entityManager->flush();

        return $position;
    }

    public function moveToEnd(Task $task, BoardColumn $target, ?Category $category = null): int
    {
        return $this->move($task, $target, \PHP_INT_MAX, $category);
    }

    /**
     * Keeps the in-memory collections consistent with the new cell (the page may be rendered right after).
     */
    private function relink(Task $task, BoardColumn $target, Category $category): void
    {
        $task->getColumn()->getTasks()->removeElement($task);
        $task->getCategory()->getTasks()->removeElement($task);
        $target->getTasks()->add($task);
        $category->getTasks()->add($task);
    }

    private function logMove(Task $task, BoardColumn $source, BoardColumn $target, Category $sourceCategory, Category $category): void
    {
        $from = $source->getName();
        $to = $target->getName();
        if ($sourceCategory !== $category) {
            $from = \sprintf('%s / %s', $sourceCategory->getName(), $from);
            $to = \sprintf('%s / %s', $category->getName(), $to);
        }

        $this->dispatcher->dispatch(new ProjectActivityEvent($task->getProject(), ActivityAction::TASK_MOVED, $task->getTitle(), [
            'from' => $from,
            'to' => $to,
        ]));
    }
}
