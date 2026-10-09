<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\BoardColumn;
use App\Entity\Label;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskPriority;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $title = '';

    #[Assert\Length(max: 10000)]
    public ?string $description = null;

    /** @var list<User> restricted to the project members by TaskFormType */
    public array $assignees = [];

    public ?\DateTimeImmutable $dueDate = null;

    public TaskPriority $priority = TaskPriority::MEDIUM;

    /** @var list<Label> restricted to the project labels by TaskFormType */
    public array $labels = [];

    public function __construct(
        #[Assert\NotNull]
        public BoardColumn $column,
    ) {
    }

    public static function fromTask(Task $task): self
    {
        $data = new self($task->getColumn());
        $data->title = $task->getTitle();
        $data->description = $task->getDescription();
        $data->assignees = $task->getAssignees()->getValues();
        $data->dueDate = $task->getDueDate();
        $data->priority = $task->getPriority();
        $data->labels = $task->getLabels()->getValues();

        return $data;
    }
}
