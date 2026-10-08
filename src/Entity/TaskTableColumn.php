<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TableColumnType;
use Doctrine\ORM\Mapping as ORM;

/**
 * The type is chosen at creation and never changes: stored values always match it.
 */
#[ORM\Entity]
class TaskTableColumn implements Positionable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'columns')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private TaskTable $table,
        #[ORM\Column(length: 50)]
        private string $name,
        #[ORM\Column(length: 20, enumType: TableColumnType::class)]
        private TableColumnType $type,
        #[ORM\Column]
        private int $position,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTable(): TaskTable
    {
        return $this->table;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function getType(): TableColumnType
    {
        return $this->type;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    /**
     * Key of this column in TaskTableRow::$cells (the id, stable across renames and moves).
     */
    public function getKey(): string
    {
        return (string) $this->id;
    }
}
