<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProjectColor;
use App\Repository\LabelRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LabelRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_LABEL_PROJECT_NAME', fields: ['project', 'name'])]
class Label
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'labels')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        #[ORM\Column(length: 30)]
        private string $name,
        #[ORM\Column(length: 20, enumType: ProjectColor::class)]
        private ProjectColor $color,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getColor(): ProjectColor
    {
        return $this->color;
    }
}
