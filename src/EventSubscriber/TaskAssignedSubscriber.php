<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use App\Service\TaskNotifier;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tells each new assignee a task was given to them, unless they assigned it to themselves.
 */
final readonly class TaskAssignedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TaskNotifier $notifier,
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [ProjectActivityEvent::class => 'onProjectActivity'];
    }

    public function onProjectActivity(ProjectActivityEvent $event): void
    {
        $assignee = $event->assignee;
        if (ActivityAction::TASK_ASSIGNED !== $event->action || null === $event->task || null === $assignee) {
            return;
        }

        $actor = $this->security->getUser();
        $actor = $actor instanceof User ? $actor : null;
        if (null !== $actor && $actor->isSameAs($assignee)) {
            return;
        }

        $this->notifier->notifyAssigned($event->task, $assignee, $actor);
    }
}
