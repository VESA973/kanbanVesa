<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\BoardColumn;
use App\Entity\ChecklistItem;
use App\Entity\Comment;
use App\Entity\Label;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\Task;
use App\Service\BoardRefreshPublisher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Collects the projects whose board changed during the request (any flushed change
 * to a task, column, comment, checklist item, label or member) and asks the
 * browsers to refresh them once the response has been sent.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class RealtimeBoardSubscriber implements ResetInterface
{
    /** @var array<int, true> */
    private array $changedProjectIds = [];

    public function __construct(
        private readonly BoardRefreshPublisher $publisher,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $entities = [
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
            ...$unitOfWork->getScheduledEntityDeletions(),
        ];
        foreach ([...$unitOfWork->getScheduledCollectionUpdates(), ...$unitOfWork->getScheduledCollectionDeletions()] as $collection) {
            $entities[] = $collection->getOwner();
        }

        foreach ($entities as $entity) {
            $projectId = $this->projectOf($entity)?->getId();
            if (null !== $projectId) {
                $this->changedProjectIds[$projectId] = true;
            }
        }
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onTerminate(TerminateEvent $event): void
    {
        $requestId = $event->getRequest()->headers->get('X-Turbo-Request-Id');
        foreach (array_keys($this->changedProjectIds) as $projectId) {
            $this->publisher->publish($projectId, $requestId);
        }

        $this->reset();
    }

    public function reset(): void
    {
        $this->changedProjectIds = [];
    }

    private function projectOf(?object $entity): ?Project
    {
        return match (true) {
            $entity instanceof Project => $entity,
            $entity instanceof Task, $entity instanceof BoardColumn, $entity instanceof Label, $entity instanceof ProjectMember => $entity->getProject(),
            $entity instanceof Comment, $entity instanceof ChecklistItem => $entity->getTask()->getProject(),
            default => null,
        };
    }
}
