<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BoardColumn;
use App\Entity\Project;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use App\Repository\BoardColumnRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class BoardColumnManager
{
    public function __construct(
        private BoardColumnRepository $columnRepository,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function create(Project $project, string $name): BoardColumn
    {
        $column = $project->addColumn($name);
        $this->dispatcher->dispatch(new ProjectActivityEvent($project, ActivityAction::COLUMN_CREATED, $name));
        $this->columnRepository->save($column);

        return $column;
    }

    public function rename(BoardColumn $column, string $name): void
    {
        $this->dispatcher->dispatch(new ProjectActivityEvent($column->getProject(), ActivityAction::COLUMN_RENAMED, $name, [
            'previous' => $column->getName(),
        ]));
        $column->rename($name);
        $this->columnRepository->save($column);
    }

    /**
     * Deletes the column with its tasks.
     */
    public function delete(BoardColumn $column): void
    {
        $this->dispatcher->dispatch(new ProjectActivityEvent($column->getProject(), ActivityAction::COLUMN_DELETED, $column->getName(), [
            'count' => (string) $column->getTasks()->count(),
        ]));
        $this->columnRepository->remove($column);
    }
}
