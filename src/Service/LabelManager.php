<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Label;
use App\Entity\Project;
use App\Enum\ProjectColor;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LabelManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(Project $project, string $name, ProjectColor $color): Label
    {
        $label = new Label($project, trim($name), $color);
        $project->getLabels()->add($label);
        $this->entityManager->persist($label);
        $this->entityManager->flush();

        return $label;
    }

    /**
     * The label is also removed from every task carrying it (ON DELETE CASCADE).
     */
    public function delete(Label $label): void
    {
        $label->getProject()->getLabels()->removeElement($label);
        $this->entityManager->remove($label);
        $this->entityManager->flush();
    }
}
