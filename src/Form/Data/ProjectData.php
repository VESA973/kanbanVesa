<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\Project;
use App\Enum\ProjectColor;
use Symfony\Component\Validator\Constraints as Assert;

final class ProjectData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $name = '';

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    public ProjectColor $color = ProjectColor::INDIGO;

    public static function fromProject(Project $project): self
    {
        $data = new self();
        $data->name = $project->getName();
        $data->description = $project->getDescription();
        $data->color = $project->getColor();

        return $data;
    }
}
