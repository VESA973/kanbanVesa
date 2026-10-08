<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaskPriority;
use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /** Set when the "due tomorrow" e-mail is sent; cleared when the due date changes. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueReminderSentAt = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'task', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $comments;

    /**
     * @var Collection<int, ChecklistItem>
     */
    #[ORM\OneToMany(targetEntity: ChecklistItem::class, mappedBy: 'task', orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $checklistItems;

    /**
     * @var Collection<int, Label>
     */
    #[ORM\ManyToMany(targetEntity: Label::class)]
    #[ORM\JoinTable(name: 'task_label')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $labels;

    /**
     * @var Collection<int, TaskTable>
     */
    #[ORM\OneToMany(targetEntity: TaskTable::class, mappedBy: 'task', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $tables;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'tasks')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private BoardColumn $column,
        #[ORM\Column(length: 255)]
        private string $title,
        #[ORM\Column]
        private int $position,
        /** Becomes null when the author's account is deleted: the task stays. */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $createdBy,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->comments = new ArrayCollection();
        $this->checklistItems = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->tables = new ArrayCollection();
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

    public function assignTo(?User $assignee): void
    {
        $this->assignee = $assignee;
    }

    public function unassign(): void
    {
        $this->assignee = null;
    }

    /**
     * Compares ids as well as instances, like ProjectMember::isFor().
     */
    public function isAssignedTo(User $user): bool
    {
        if (null === $this->assignee) {
            return false;
        }

        return $this->assignee === $user || (null !== $user->getId() && $this->assignee->getId() === $user->getId());
    }

    public function schedule(?\DateTimeImmutable $dueDate): void
    {
        $dueDate = $dueDate?->setTime(0, 0);
        if ($dueDate?->format('Y-m-d') !== $this->dueDate?->format('Y-m-d')) {
            $this->dueReminderSentAt = null;
        }

        $this->dueDate = $dueDate;
    }

    public function markDueReminderAsSent(): void
    {
        $this->dueReminderSentAt = new \DateTimeImmutable();
    }

    public function getDueReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->dueReminderSentAt;
    }

    public function isOverdue(\DateTimeInterface $today): bool
    {
        return !$this->isCompleted()
            && null !== $this->dueDate
            && $this->dueDate < \DateTimeImmutable::createFromInterface($today)->setTime(0, 0);
    }

    public function prioritize(TaskPriority $priority): void
    {
        $this->priority = $priority;
    }

    public function complete(): void
    {
        $this->completedAt ??= new \DateTimeImmutable();
    }

    public function reopen(): void
    {
        $this->completedAt = null;
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /**
     * @return Collection<int, ChecklistItem>
     */
    public function getChecklistItems(): Collection
    {
        return $this->checklistItems;
    }

    public function countDoneChecklistItems(): int
    {
        return $this->checklistItems->filter(static fn (ChecklistItem $item): bool => $item->isDone())->count();
    }

    /**
     * @return Collection<int, Label>
     */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    /**
     * @return Collection<int, TaskTable>
     */
    public function getTables(): Collection
    {
        return $this->tables;
    }

    public function addTable(string $title): TaskTable
    {
        $table = new TaskTable($this, $title, $this->tables->count());
        $this->tables->add($table);

        return $table;
    }

    /**
     * @param iterable<Label> $labels labels of the task's project
     */
    public function replaceLabels(iterable $labels): void
    {
        $this->labels->clear();
        foreach ($labels as $label) {
            if ($label->getProject() !== $this->getProject()) {
                throw new \InvalidArgumentException('A label from another project cannot be put on this task.');
            }

            $this->labels->add($label);
        }
    }
}
