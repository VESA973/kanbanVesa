<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\Program;
use App\Enum\ProjectColor;
use Symfony\Component\Validator\Constraints as Assert;

final class ProgramData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $name = '';

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    public ProjectColor $color = ProjectColor::INDIGO;

    public static function fromProgram(Program $program): self
    {
        $data = new self();
        $data->name = $program->getName();
        $data->description = $program->getDescription();
        $data->color = $program->getColor();

        return $data;
    }
}
