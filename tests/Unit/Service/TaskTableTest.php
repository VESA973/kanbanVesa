<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskTable;
use App\Entity\TaskTableColumn;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Enum\TableColumnType;
use App\Enum\TableTemplate;
use App\Exception\TaskTableException;
use App\Service\TableCellNormalizer;
use App\Service\TaskTableCsvExporter;
use App\Service\TaskTableManager;
use App\Twig\TableCellFormatter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TaskTableTest extends TestCase
{
    private User $owner;
    private Task $task;

    protected function setUp(): void
    {
        $this->owner = new User('owner@example.com', 'Olivia', 'Owner');
        $this->task = new Task(new Project('Chantier', $this->owner)->addColumn('À faire'), 'Préparer le matériel', 0, $this->owner);
    }

    /**
     * @return iterable<string, array{TableColumnType, ?string, string|int|float|bool|null}>
     */
    public static function validValues(): iterable
    {
        yield 'text is trimmed' => [TableColumnType::TEXT, '  Perceuse ', 'Perceuse'];
        yield 'empty text empties the cell' => [TableColumnType::TEXT, '   ', null];
        yield 'French number' => [TableColumnType::NUMBER, '1 234,5', 1234.5];
        yield 'integer' => [TableColumnType::NUMBER, '3', 3];
        yield 'negative' => [TableColumnType::NUMBER, '-2', -2];
        yield 'date' => [TableColumnType::DATE, '2026-11-02', '2026-11-02'];
        yield 'ticked' => [TableColumnType::CHECKBOX, '1', true];
        yield 'unticked' => [TableColumnType::CHECKBOX, '0', null];
    }

    #[DataProvider('validValues')]
    public function testValuesAreNormalizedForTheirColumnType(TableColumnType $type, ?string $raw, string|int|float|bool|null $expected): void
    {
        $column = $this->task->addTable('Tableau')->addColumn('Colonne', $type);

        self::assertSame($expected, TableCellNormalizer::normalize($column, $raw));
    }

    /**
     * @return iterable<string, array{TableColumnType, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'letters in a number' => [TableColumnType::NUMBER, 'deux'];
        yield 'impossible date' => [TableColumnType::DATE, '2026-02-31'];
        yield 'French date format' => [TableColumnType::DATE, '02/11/2026'];
        yield 'too long text' => [TableColumnType::TEXT, str_repeat('a', 501)];
        yield 'not a member' => [TableColumnType::MEMBER, '999'];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreRefused(TableColumnType $type, string $raw): void
    {
        $column = $this->task->addTable('Tableau')->addColumn('Colonne', $type);

        $this->expectException(TaskTableException::class);

        TableCellNormalizer::normalize($column, $raw);
    }

    public function testAMemberColumnAcceptsOnlyMembersOfTheChantier(): void
    {
        $alex = new User('alex@example.com', 'Alex', 'Martin');
        new \ReflectionProperty(User::class, 'id')->setValue($alex, 42);
        $this->task->getProject()->addMember($alex, ProjectRole::EDITOR);
        $column = $this->task->addTable('Équipe')->addColumn('Responsable', TableColumnType::MEMBER);

        self::assertSame(42, TableCellNormalizer::normalize($column, '42'));
        self::assertSame('Alex Martin', $this->formatter()->display($column, 42));
        self::assertSame('ancien membre', $this->formatter()->display($column, 7));
    }

    public function testATemplateCreatesItsColumnsAndAFirstRow(): void
    {
        $table = $this->manager()->create($this->task, 'Équipe', TableTemplate::TEAM);

        self::assertSame(
            ['task_table.column.name', 'task_table.column.role', 'task_table.column.phone', 'task_table.column.email', 'task_table.column.available'],
            $table->getColumns()->map(static fn ($column): string => $column->getName())->getValues(),
        );
        self::assertSame(TableColumnType::CHECKBOX, ($table->getColumns()->last() ?: throw new \LogicException())->getType());
        self::assertCount(1, $table->getRows());
    }

    public function testDeletingAColumnErasesItsValuesButTheLastOneStays(): void
    {
        $table = $this->table();
        [$tool, $quantity] = $table->getColumns()->getValues();
        $row = $table->getRows()->first() ?: throw new \LogicException();
        $row->setValue($quantity, 3);

        $this->manager()->deleteColumn($quantity);

        self::assertNull($row->getValue($quantity));
        self::assertCount(1, $table->getColumns());
        $this->expectExceptionObject(TaskTableException::lastColumn());
        $this->manager()->deleteColumn($tool);
    }

    public function testNumberColumnsAreTotalled(): void
    {
        $table = $this->table();
        $quantity = $table->getColumns()->get(1) ?? throw new \LogicException();
        ($table->getRows()->first() ?: throw new \LogicException())->setValue($quantity, 2);
        $table->addRow()->setValue($quantity, 1.5);

        self::assertSame(3.5, $table->totalOf($quantity));
        self::assertSame('3,5', TableCellFormatter::number($table->totalOf($quantity)));
        self::assertSame("1\u{202F}234", TableCellFormatter::number(1234));
    }

    public function testCsvExportIsReadyForAFrenchSpreadsheetAndDisarmsFormulas(): void
    {
        $table = $this->table();
        [$tool, $quantity] = $table->getColumns()->getValues();
        $row = $table->getRows()->first() ?: throw new \LogicException();
        $row->setValue($tool, '=HYPERLINK("http://evil")');
        $row->setValue($quantity, -2);

        $csv = new TaskTableCsvExporter($this->formatter())->export($table);

        self::assertStringStartsWith("\u{FEFF}Outil;Quantité\n", $csv);
        self::assertStringContainsString("\"'=HYPERLINK(\"\"http://evil\"\")\";-2", $csv);
    }

    private function table(): TaskTable
    {
        $table = $this->task->addTable('Outils');
        // Cells are keyed by column id: give the in-memory columns the ids the database would.
        $id = new \ReflectionProperty(TaskTableColumn::class, 'id');
        $id->setValue($table->addColumn('Outil', TableColumnType::TEXT), 1);
        $id->setValue($table->addColumn('Quantité', TableColumnType::NUMBER), 2);
        $table->addRow();

        return $table;
    }

    private function manager(): TaskTableManager
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new TaskTableManager($this->createStub(EntityManagerInterface::class), $translator);
    }

    private function formatter(): TableCellFormatter
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => ['task_table.former_member' => 'ancien membre', 'task_table.yes' => 'Oui', 'task_table.no' => 'Non'][$key] ?? $key);

        return new TableCellFormatter($translator);
    }
}
