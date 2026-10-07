<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\Label;
use App\Entity\Project;
use App\Enum\ProjectColor;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['project', 'name'], message: 'label.name.already_used', entityClass: Label::class, errorPath: 'name')]
final class LabelData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    public string $name = '';

    public ProjectColor $color = ProjectColor::SKY;

    public function __construct(
        public readonly Project $project,
    ) {
    }
}
