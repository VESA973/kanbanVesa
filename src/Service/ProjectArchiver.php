<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * An archived project leaves "Mes projets" and "Mes tâches" and becomes read-only (see the voters).
 */
final readonly class ProjectArchiver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function archive(Project $project): void
    {
        $project->archive();
        $this->dispatcher->dispatch(new ProjectActivityEvent($project, ActivityAction::PROJECT_ARCHIVED, $project->getName()));
        $this->entityManager->flush();
    }

    public function unarchive(Project $project): void
    {
        $project->unarchive();
        $this->dispatcher->dispatch(new ProjectActivityEvent($project, ActivityAction::PROJECT_UNARCHIVED, $project->getName()));
        $this->entityManager->flush();
    }
}
