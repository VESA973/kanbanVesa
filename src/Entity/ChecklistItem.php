<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ChecklistItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChecklistItemRepository::class)]
#[ORM\Index(name: 'IDX_CHECKLIST_ITEM_POSITION', fields: ['task', 'position'])]
class ChecklistItem implements Positionable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private bool $isDone = false;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'checklistItems')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Task $task,
        #[ORM\Column(length: 255)]
        private string $label,
        #[ORM\Column]
        private int $position,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isDone(): bool
    {
        return $this->isDone;
    }

    public function toggle(): void
    {
        $this->isDone = !$this->isDone;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
