<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Form\Data\TaskData;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TaskUpdater
{
    public function __construct(
        private TaskMover $taskMover,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Choosing another column from the form moves the task to the bottom of it.
     */
    public function update(Task $task, TaskData $data): void
    {
        $task->update($data->title, $data->description);

        if ($data->column !== $task->getColumn()) {
            $this->taskMover->moveToEnd($task, $data->column);

            return;
        }

        $this->entityManager->flush();
    }
}
