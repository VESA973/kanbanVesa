<?php

declare(strict_types=1);

namespace App\Enum;

enum TableColumnType: string
{
    case TEXT = 'text';
    case NUMBER = 'number';
    case DATE = 'date';
    case CHECKBOX = 'checkbox';
    /** A member of the chantier (stored as the user id). */
    case MEMBER = 'member';

    public function translationKey(): string
    {
        return 'task_table.type.'.$this->value;
    }
}
