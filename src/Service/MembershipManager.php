<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProjectMember;
use App\Enum\ActivityAction;
use App\Enum\ProjectRole;
use App\Event\ProjectActivityEvent;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class MembershipManager
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $dispatcher,
        private TranslatorInterface $translator,
    ) {
    }

    public function changeRole(ProjectMember $member, ProjectRole $role): void
    {
        $member->changeRole($role);
        $this->dispatcher->dispatch(new ProjectActivityEvent($member->getProject(), ActivityAction::MEMBER_ROLE_CHANGED, $member->getUser()->getFullName(), [
            'role' => $this->translator->trans($role->translationKey()),
        ]));
        $this->entityManager->flush();
    }

    /**
     * A removed member keeps no task assigned in the project.
     */
    public function remove(ProjectMember $member): void
    {
        $project = $member->getProject();
        $project->removeMember($member);
        $this->taskRepository->unassignInProject($project, $member->getUser());
        $this->dispatcher->dispatch(new ProjectActivityEvent($project, ActivityAction::MEMBER_REMOVED, $member->getUser()->getFullName()));
        $this->entityManager->flush();
    }
}
