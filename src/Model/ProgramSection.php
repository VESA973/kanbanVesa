<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Program;
use App\Entity\Project;

/**
 * A program and the projects of it the user can open, as listed on "Mes projets".
 */
final readonly class ProgramSection
{
    /**
     * @param list<Project> $projects
     */
    public function __construct(
        public Program $program,
        public array $projects,
        public ProjectsProgress $progress,
        /** False when the user only belongs to some of its projects: the program page is not theirs to open. */
        public bool $isMember,
    ) {
    }

    /**
     * All the listed projects together, weighted by their number of tasks (not an average of percentages).
     */
    public function overall(): Progress
    {
        return array_reduce($this->projects, fn (Progress $sum, Project $project): Progress => $sum->add($this->progress->of($project)), new Progress());
    }
}
