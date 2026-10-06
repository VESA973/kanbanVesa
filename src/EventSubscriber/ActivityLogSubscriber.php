<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Event\ProjectActivityEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Persists every project activity; the service that dispatched it flushes.
 */
final readonly class ActivityLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [ProjectActivityEvent::class => 'onProjectActivity'];
    }

    public function onProjectActivity(ProjectActivityEvent $event): void
    {
        $user = $this->security->getUser();

        $this->entityManager->persist(new ActivityLog(
            $event->project,
            $user instanceof User ? $user : null,
            $event->action,
            mb_substr($event->subject, 0, 255),
            $event->payload,
        ));
    }
}
