<?php

declare(strict_types=1);

namespace App\Enum;

enum ProjectRole: string
{
    case OWNER = 'owner';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    public function translationKey(): string
    {
        return 'project.role.'.$this->value;
    }
}
