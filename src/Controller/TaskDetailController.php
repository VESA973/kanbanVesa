<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Payload\CommentPayload;
use App\Controller\Payload\NamePayload;
use App\Entity\ChecklistItem;
use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use App\Security\Voter\CommentVoter;
use App\Security\Voter\TaskVoter;
use App\Service\ChecklistManager;
use App\Service\TaskCommenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Comments and checklist of a task. Every action goes back to the task, which
 * re-renders inside the modal Turbo Frame when the form was submitted from it.
 */
final class TaskDetailController extends AbstractController
{
    #[Route('/tasks/{id}/comments', name: 'app_comment_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, 'task', statusCode: 404)]
    #[IsGranted(TaskVoter::COMMENT, 'task')]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["task"].getId()'))]
    public function comment(Task $task, #[MapRequestPayload] CommentPayload $payload, TaskCommenter $commenter, #[CurrentUser] User $user): Response
    {
        $commenter->add($task, $user, $payload->content);

        return $this->backToTask($task);
    }

    #[Route('/comments/{id}/delete', name: 'app_comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["comment"].getTask()'), statusCode: 404)]
    #[IsGranted(CommentVoter::DELETE, 'comment')]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["comment"].getTask().getId()'))]
    public function deleteComment(Comment $comment, TaskCommenter $commenter): Response
    {
        $task = $comment->getTask();
        $commenter->remove($comment);

        return $this->backToTask($task);
    }

    #[Route('/tasks/{id}/checklist', name: 'app_checklist_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, 'task', statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, 'task')]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["task"].getId()'))]
    public function addChecklistItem(Task $task, #[MapRequestPayload] NamePayload $payload, ChecklistManager $checklistManager): Response
    {
        $checklistManager->add($task, $payload->name);

        return $this->backToTask($task);
    }

    #[Route('/checklist-items/{id}/toggle', name: 'app_checklist_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["item"].getTask()'), statusCode: 404)]
    #[IsGranted(TaskVoter::COMPLETE, new Expression('args["item"].getTask()'))]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["item"].getTask().getId()'))]
    public function toggleChecklistItem(ChecklistItem $item, ChecklistManager $checklistManager): Response
    {
        $checklistManager->toggle($item);

        return $this->backToTask($item->getTask());
    }

    #[Route('/checklist-items/{id}/delete', name: 'app_checklist_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, new Expression('args["item"].getTask()'), statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, new Expression('args["item"].getTask()'))]
    #[IsCsrfTokenValid(new Expression('"task-" ~ args["item"].getTask().getId()'))]
    public function deleteChecklistItem(ChecklistItem $item, ChecklistManager $checklistManager): Response
    {
        $task = $item->getTask();
        $checklistManager->remove($item);

        return $this->backToTask($task);
    }

    private function backToTask(Task $task): Response
    {
        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }
}
