<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TaskTable;
use App\Entity\TaskTableColumn;
use App\Enum\TableColumnType;
use App\Twig\TableCellFormatter;

/**
 * CSV for Excel / LibreOffice in French: ";" separator, UTF-8 BOM, values as displayed.
 */
final readonly class TaskTableCsvExporter
{
    public function __construct(
        private TableCellFormatter $formatter,
    ) {
    }

    public function export(TaskTable $table): string
    {
        $stream = fopen('php://temp', 'r+') ?: throw new \RuntimeException('Cannot open a temporary stream.');
        fwrite($stream, "\u{FEFF}");

        $columns = $table->getColumns()->getValues();
        fputcsv($stream, array_map(static fn (TaskTableColumn $column): string => self::safe($column->getName()), $columns), ';', '"', '');
        foreach ($table->getRows() as $row) {
            $cells = array_map(fn (TaskTableColumn $column): string => $this->cell($column, $row->getValue($column)), $columns);
            fputcsv($stream, $cells, ';', '"', '');
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function cell(TaskTableColumn $column, string|int|float|bool|null $value): string
    {
        $text = $this->formatter->display($column, $value);

        // Numbers are produced by the formatter, never typed as-is: "-5" must stay a number.
        return TableColumnType::NUMBER === $column->getType() ? $text : self::safe($text);
    }

    /**
     * A cell starting with = + - @ would run as a formula in a spreadsheet (CSV injection).
     */
    private static function safe(string $value): string
    {
        return '' !== $value && \in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
