<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A fixed palette rather than free hex codes: Tailwind only generates
 * classes it can find literally in the templates (see ProjectColorSwatch).
 */
enum ProjectColor: string
{
    case INDIGO = 'indigo';
    case SKY = 'sky';
    case EMERALD = 'emerald';
    case AMBER = 'amber';
    case ROSE = 'rose';
    case VIOLET = 'violet';
    case SLATE = 'slate';

    public function translationKey(): string
    {
        return 'project.color.'.$this->value;
    }
}
