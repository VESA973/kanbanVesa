<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskTable;
use App\Entity\TaskTableColumn;
use App\Entity\TaskTableRow;
use App\Enum\TableColumnType;
use App\Enum\TableTemplate;
use App\Exception\TaskTableException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tables inside tasks: structure (columns, rows) and cell values.
 */
final readonly class TaskTableManager
{
    public const int MAX_COLUMNS = 20;
    public const int MAX_ROWS = 500;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * A new table starts with the columns of its template and one empty row.
     */
    public function create(Task $task, string $title, TableTemplate $template): TaskTable
    {
        $table = $task->addTable($title);
        foreach ($template->columns() as [$nameKey, $type]) {
            $table->addColumn($this->translator->trans($nameKey), $type);
        }
        $table->addRow();
        $this->entityManager->persist($table);
        $this->entityManager->flush();

        return $table;
    }

    public function rename(TaskTable $table, string $title): void
    {
        $table->rename($title);
        $this->entityManager->flush();
    }

    public function delete(TaskTable $table): void
    {
        $tables = $table->getTask()->getTables();
        $tables->removeElement($table);
        PositionList::remove($tables, $table);
        $this->entityManager->flush();
    }

    /**
     * @throws TaskTableException when the table already has MAX_COLUMNS columns
     */
    public function addColumn(TaskTable $table, string $name, TableColumnType $type): TaskTableColumn
    {
        if ($table->getColumns()->count() >= self::MAX_COLUMNS) {
            throw TaskTableException::tooManyColumns();
        }

        $column = $table->addColumn($name, $type);
        $this->entityManager->flush();

        return $column;
    }

    public function renameColumn(TaskTableColumn $column, string $name): void
    {
        $column->rename($name);
        $this->entityManager->flush();
    }

    /**
     * @param -1|1 $step left or right
     */
    public function moveColumn(TaskTableColumn $column, int $step): void
    {
        PositionList::insert($column->getTable()->getColumns(), $column, $column->getPosition() + $step);
        $this->entityManager->flush();
    }

    /**
     * Its values are erased from every row.
     *
     * @throws TaskTableException for the last column of the table
     */
    public function deleteColumn(TaskTableColumn $column): void
    {
        $table = $column->getTable();
        if ($table->getColumns()->count() <= 1) {
            throw TaskTableException::lastColumn();
        }

        foreach ($table->getRows() as $row) {
            $row->setValue($column, null);
        }
        $table->getColumns()->removeElement($column);
        PositionList::remove($table->getColumns(), $column);
        $this->entityManager->flush();
    }

    /**
     * @throws TaskTableException when the table already has MAX_ROWS rows
     */
    public function addRow(TaskTable $table): TaskTableRow
    {
        if ($table->getRows()->count() >= self::MAX_ROWS) {
            throw TaskTableException::tooManyRows();
        }

        $row = $table->addRow();
        $this->entityManager->flush();

        return $row;
    }

    public function deleteRow(TaskTableRow $row): void
    {
        $rows = $row->getTable()->getRows();
        $rows->removeElement($row);
        PositionList::remove($rows, $row);
        $this->entityManager->flush();
    }

    /**
     * @return string|int|float|bool|null the value actually stored
     *
     * @throws TaskTableException        when the value does not fit the column type
     * @throws \InvalidArgumentException when the column belongs to another table
     */
    public function updateCell(TaskTableRow $row, TaskTableColumn $column, ?string $raw): string|int|float|bool|null
    {
        if ($column->getTable() !== $row->getTable()) {
            throw new \InvalidArgumentException('The column belongs to another table.');
        }

        $value = TableCellNormalizer::normalize($column, $raw);
        $row->setValue($column, $value);
        $this->entityManager->flush();

        return $value;
    }
}
