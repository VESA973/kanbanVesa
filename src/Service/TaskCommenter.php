<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class TaskCommenter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function add(Task $task, User $author, string $content): Comment
    {
        $comment = new Comment($task, $author, trim($content));
        $task->getComments()->add($comment);
        $this->entityManager->persist($comment);
        $this->dispatcher->dispatch(new ProjectActivityEvent($task->getProject(), ActivityAction::COMMENT_ADDED, $task->getTitle(), task: $task));
        $this->entityManager->flush();

        return $comment;
    }

    public function remove(Comment $comment): void
    {
        $comment->getTask()->getComments()->removeElement($comment);
        $this->entityManager->remove($comment);
        $this->entityManager->flush();
    }
}
