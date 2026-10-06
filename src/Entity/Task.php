<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaskPriority;
use App\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Index(name: 'IDX_TASK_POSITION', fields: ['column', 'position'])]
#[ORM\Index(name: 'IDX_TASK_DUE_DATE', fields: ['dueDate'])]
class Task implements Positionable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $assignee = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(length: 10, enumType: TaskPriority::class)]
    private TaskPriority $priority = TaskPriority::MEDIUM;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'tasks')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private BoardColumn $column,
        #[ORM\Column(length: 255)]
        private string $title,
        #[ORM\Column]
        private int $position,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private User $createdBy,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getColumn(): BoardColumn
    {
        return $this->column;
    }

    public function getProject(): Project
    {
        return $this->column->getProject();
    }

    public function moveTo(BoardColumn $column, int $position): void
    {
        $this->column = $column;
        $this->position = $position;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function update(string $title, ?string $description): void
    {
        $this->title = $title;
        $description = trim((string) $description);
        $this->description = '' === $description ? null : $description;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function isCompleted(): bool
    {
        return null !== $this->completedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
