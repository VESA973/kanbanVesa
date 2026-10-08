<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TableColumnType;
use App\Repository\TaskTableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A data table inside a task (a team, a list of tools…): typed columns and free rows.
 */
#[ORM\Entity(repositoryClass: TaskTableRepository::class)]
class TaskTable implements Positionable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, TaskTableColumn>
     */
    #[ORM\OneToMany(targetEntity: TaskTableColumn::class, mappedBy: 'table', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $columns;

    /**
     * @var Collection<int, TaskTableRow>
     */
    #[ORM\OneToMany(targetEntity: TaskTableRow::class, mappedBy: 'table', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $rows;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'tables')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Task $task,
        #[ORM\Column(length: 100)]
        private string $title,
        #[ORM\Column]
        private int $position,
    ) {
        $this->columns = new ArrayCollection();
        $this->rows = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function rename(string $title): void
    {
        $this->title = $title;
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
     * @return Collection<int, TaskTableColumn>
     */
    public function getColumns(): Collection
    {
        return $this->columns;
    }

    public function addColumn(string $name, TableColumnType $type): TaskTableColumn
    {
        $column = new TaskTableColumn($this, $name, $type, $this->columns->count());
        $this->columns->add($column);

        return $column;
    }

    /**
     * @return Collection<int, TaskTableRow>
     */
    public function getRows(): Collection
    {
        return $this->rows;
    }

    public function addRow(): TaskTableRow
    {
        $row = new TaskTableRow($this, $this->rows->count());
        $this->rows->add($row);

        return $row;
    }

    /**
     * Sum of a number column, for the footer of the table.
     */
    public function totalOf(TaskTableColumn $column): float
    {
        $total = 0.0;
        foreach ($this->rows as $row) {
            $value = $row->getValue($column);
            $total += \is_int($value) || \is_float($value) ? $value : 0;
        }

        return $total;
    }
}
