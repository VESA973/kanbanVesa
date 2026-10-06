<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use App\Form\Data\ProjectData;
use App\Repository\ProjectRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ProjectCreator
{
    private const array DEFAULT_COLUMNS = ['column.default.todo', 'column.default.in_progress', 'column.default.done'];

    public function __construct(
        private ProjectRepository $projectRepository,
        private TranslatorInterface $translator,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * The owner automatically becomes a member with the OWNER role (see Project::__construct)
     * and the board starts with the usual three columns.
     */
    public function create(ProjectData $data, User $owner): Project
    {
        $project = new Project($data->name, $owner, $data->color, $data->description);
        foreach (self::DEFAULT_COLUMNS as $name) {
            $project->addColumn($this->translator->trans($name));
        }

        $this->dispatcher->dispatch(new ProjectActivityEvent($project, ActivityAction::PROJECT_CREATED, $project->getName()));
        $this->projectRepository->save($project);

        return $project;
    }
}
