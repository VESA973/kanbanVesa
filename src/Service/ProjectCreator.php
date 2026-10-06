<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Form\Data\ProjectData;
use App\Repository\ProjectRepository;

final readonly class ProjectCreator
{
    public function __construct(
        private ProjectRepository $projectRepository,
    ) {
    }

    /**
     * The owner automatically becomes a member with the OWNER role (see Project::__construct).
     */
    public function create(ProjectData $data, User $owner): Project
    {
        $project = new Project($data->name, $owner, $data->color, $data->description);
        $this->projectRepository->save($project);

        return $project;
    }
}
