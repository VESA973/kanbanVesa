<?php

declare(strict_types=1);

namespace App\Enum;

enum TaskPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function translationKey(): string
    {
        return 'task.priority.'.$this->value;
    }
}
