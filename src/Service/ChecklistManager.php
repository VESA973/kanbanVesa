<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ChecklistItem;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ChecklistManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * New items go to the bottom of the checklist.
     */
    public function add(Task $task, string $label): ChecklistItem
    {
        $item = new ChecklistItem($task, trim($label), $task->getChecklistItems()->count());
        $task->getChecklistItems()->add($item);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    public function toggle(ChecklistItem $item): void
    {
        $item->toggle();
        $this->entityManager->flush();
    }

    public function remove(ChecklistItem $item): void
    {
        $items = $item->getTask()->getChecklistItems();
        $items->removeElement($item);
        PositionList::remove($items, $item);
        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }
}
