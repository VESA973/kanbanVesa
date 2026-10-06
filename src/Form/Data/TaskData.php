<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\BoardColumn;
use App\Entity\Task;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $title = '';

    #[Assert\Length(max: 10000)]
    public ?string $description = null;

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

        return $data;
    }
}
