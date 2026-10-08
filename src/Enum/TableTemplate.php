<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Ready-made tables offered when creating one in a task.
 */
enum TableTemplate: string
{
    case EMPTY = 'empty';
    case TEAM = 'team';
    case TOOLS = 'tools';

    public function translationKey(): string
    {
        return 'task_table.template.'.$this->value;
    }

    /**
     * @return list<array{string, TableColumnType}> column name translation keys and types
     */
    public function columns(): array
    {
        return match ($this) {
            self::EMPTY => [['task_table.column.default', TableColumnType::TEXT]],
            self::TEAM => [
                ['task_table.column.name', TableColumnType::TEXT],
                ['task_table.column.role', TableColumnType::TEXT],
                ['task_table.column.phone', TableColumnType::TEXT],
                ['task_table.column.email', TableColumnType::TEXT],
                ['task_table.column.available', TableColumnType::CHECKBOX],
            ],
            self::TOOLS => [
                ['task_table.column.tool', TableColumnType::TEXT],
                ['task_table.column.quantity', TableColumnType::NUMBER],
                ['task_table.column.condition', TableColumnType::TEXT],
                ['task_table.column.ready', TableColumnType::CHECKBOX],
            ],
        };
    }
}
