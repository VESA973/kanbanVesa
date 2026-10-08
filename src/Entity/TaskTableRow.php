<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The cells are stored as JSON keyed by column id (a LONGTEXT on MariaDB: never queried,
 * only read with the row). Values are already normalized by TableCellNormalizer.
 */
#[ORM\Entity]
class TaskTableRow implements Positionable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var array<string, string|int|float|bool>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $cells = [];

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'rows')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private TaskTable $table,
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getValue(TaskTableColumn $column): string|int|float|bool|null
    {
        return $this->cells[$column->getKey()] ?? null;
    }

    /**
     * @param string|int|float|bool|null $value null empties the cell
     */
    public function setValue(TaskTableColumn $column, string|int|float|bool|null $value): void
    {
        if (null === $value) {
            unset($this->cells[$column->getKey()]);

            return;
        }

        $this->cells[$column->getKey()] = $value;
    }
}
