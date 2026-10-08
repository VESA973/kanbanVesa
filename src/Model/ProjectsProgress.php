<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Project;

/**
 * Progress of each project of a list.
 */
final readonly class ProjectsProgress
{
    /**
     * @param array<int, Progress> $byProject indexed by project id; missing projects have no task
     */
    public function __construct(
        private array $byProject,
    ) {
    }

    public function of(Project $project): Progress
    {
        return $this->byProject[(int) $project->getId()] ?? new Progress();
    }
}
