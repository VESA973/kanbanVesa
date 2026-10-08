<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Program;
use App\Entity\Project;
use App\Entity\User;
use App\Model\PoleGroup;
use App\Model\ProgramSection;
use App\Model\ProjectsProgress;
use App\Repository\PoleRepository;
use App\Repository\ProgramRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;

/**
 * The active projects of a user grouped by program, with their progress.
 */
final readonly class ProjectDirectory
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProgramRepository $programRepository,
        private TaskRepository $taskRepository,
        private PoleRepository $poleRepository,
    ) {
    }

    /**
     * @return list<ProgramSection> sorted by program name; programs of the user without projects included
     */
    public function sectionsFor(User $user): array
    {
        $projects = $this->projectRepository->findActiveForMember($user);
        $progress = new ProjectsProgress($this->taskRepository->countByProject($projects));

        /** @var array<int, array{Program, list<Project>}> $groups */
        $groups = [];
        foreach ($this->programRepository->findForMember($user) as $program) {
            $groups[(int) $program->getId()] = [$program, []];
        }
        foreach ($projects as $project) {
            $program = $project->getProgram();
            $groups[(int) $program->getId()] ??= [$program, []];
            $groups[(int) $program->getId()][1][] = $project;
        }

        $sections = array_map(
            static fn (array $group): ProgramSection => new ProgramSection($group[0], $group[1], $progress, null !== $group[0]->getRoleOf($user)),
            array_values($groups),
        );
        usort($sections, static fn (ProgramSection $a, ProgramSection $b): int => strcasecmp($a->program->getName(), $b->program->getName()));

        return $sections;
    }

    /**
     * The sections of the user grouped by their poles (in their order), then "Sans pôle".
     * Empty poles are kept so the user sees where to file programs.
     *
     * @param list<ProgramSection> $sections
     *
     * @return list<PoleGroup>
     */
    public function groupByPole(array $sections, User $user): array
    {
        $groups = [];
        $filed = [];
        foreach ($this->poleRepository->findForUser($user) as $pole) {
            $inPole = array_values(array_filter($sections, static fn (ProgramSection $section): bool => $pole->contains($section->program)));
            array_push($filed, ...$inPole);
            $groups[] = new PoleGroup($pole, $inPole);
        }

        $unfiled = array_values(array_filter($sections, static fn (ProgramSection $section): bool => !\in_array($section, $filed, true)));
        if ([] !== $unfiled || [] === $groups) {
            $groups[] = new PoleGroup(null, $unfiled);
        }

        return $groups;
    }

    /**
     * The active projects of a program the user can open: all of them for a program member,
     * only those they were invited to otherwise.
     */
    public function projectsOf(Program $program, User $user): ProgramSection
    {
        $isMember = null !== $program->getRoleOf($user);
        $projects = $this->projectRepository->findActiveInProgram($program);
        if (!$isMember) {
            $projects = array_values(array_filter($projects, static fn (Project $project): bool => null !== $project->getRoleOf($user)));
        }

        return new ProgramSection($program, $projects, new ProjectsProgress($this->taskRepository->countByProject($projects)), $isMember);
    }
}
