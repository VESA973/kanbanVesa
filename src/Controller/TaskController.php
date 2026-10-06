<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Payload\MovePayload;
use App\Controller\Payload\NamePayload;
use App\Entity\BoardColumn;
use App\Entity\Task;
use App\Entity\User;
use App\Form\Data\TaskData;
use App\Form\TaskFormType;
use App\Repository\BoardColumnRepository;
use App\Security\Voter\ProjectVoter;
use App\Security\Voter\TaskVoter;
use App\Service\TaskCompleter;
use App\Service\TaskCreator;
use App\Service\TaskMover;
use App\Service\TaskRemover;
use App\Service\TaskUpdater;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TaskController extends AbstractController
{
    #[Route('/columns/{id}/tasks', name: 'app_task_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, new Expression('args["column"].getProject()'), statusCode: 404)]
    #[IsGranted(ProjectVoter::CREATE_TASK, new Expression('args["column"].getProject()'))]
    #[IsCsrfTokenValid(new Expression('"board-" ~ args["column"].getProject().getId()'))]
    public function new(BoardColumn $column, #[MapRequestPayload] NamePayload $payload, TaskCreator $taskCreator, #[CurrentUser] User $user): Response
    {
        $taskCreator->create($column, $payload->name, $user);

        return $this->redirectToRoute('app_project_show', ['id' => $column->getProject()->getId()]);
    }

    /**
     * Rendered inside the board modal when requested by a Turbo Frame, as a full page otherwise.
     */
    #[Route('/tasks/{id}', name: 'app_task_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(TaskVoter::VIEW, 'task', statusCode: 404)]
    public function show(Request $request, Task $task, TaskUpdater $taskUpdater): Response
    {
        $data = TaskData::fromTask($task);
        $form = $this->createForm(TaskFormType::class, $data, ['project' => $task->getProject()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('task/show.html.twig', ['task' => $task, 'form' => $form]);
        }

        $this->denyAccessUnlessGranted(TaskVoter::EDIT, $task);
        $taskUpdater->update($task, $data);

        return $this->redirectToRoute('app_project_show', ['id' => $task->getProject()->getId()]);
    }

    #[Route('/tasks/{id}/delete', name: 'app_task_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, 'task', statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, 'task')]
    #[IsCsrfTokenValid(new Expression('"board-" ~ args["task"].getProject().getId()'))]
    public function delete(Task $task, TaskRemover $taskRemover): Response
    {
        $projectId = $task->getProject()->getId();
        $taskRemover->remove($task);
        $this->addFlash('success', 'flash.task.deleted');

        return $this->redirectToRoute('app_project_show', ['id' => $projectId]);
    }

    /**
     * Used from the board, the task modal and "Mes tâches": goes back to the page it came from.
     */
    #[Route('/tasks/{id}/toggle', name: 'app_task_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, 'task', statusCode: 404)]
    #[IsGranted(TaskVoter::COMPLETE, 'task')]
    #[IsCsrfTokenValid(new Expression('"toggle-task-" ~ args["task"].getId()'))]
    public function toggle(Request $request, Task $task, TaskCompleter $taskCompleter): Response
    {
        $completed = $taskCompleter->toggle($task);
        $this->addFlash('success', $completed ? 'flash.task.completed' : 'flash.task.reopened');

        // Only local paths: "//host" or "/\host" would be followed to another site.
        $returnTo = $request->request->getString('_return_to');

        return 1 === preg_match('#^/(?![/\\\\])#', $returnTo)
            ? $this->redirect($returnTo)
            : $this->redirectToRoute('app_project_show', ['id' => $task->getProject()->getId()]);
    }

    #[Route('/tasks/{id}/move', name: 'app_task_move', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(TaskVoter::VIEW, 'task', statusCode: 404)]
    #[IsGranted(TaskVoter::EDIT, 'task')]
    #[IsCsrfTokenValid(new Expression('"board-" ~ args["task"].getProject().getId()'), tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function move(Task $task, #[MapRequestPayload] MovePayload $payload, BoardColumnRepository $columnRepository, TaskMover $taskMover): JsonResponse
    {
        $target = null === $payload->columnId ? $task->getColumn() : $columnRepository->find($payload->columnId);
        if (null === $target || $target->getProject() !== $task->getProject()) {
            throw new UnprocessableEntityHttpException('Unknown column for this project.');
        }

        $position = $taskMover->move($task, $target, $payload->position);

        return $this->json(['columnId' => $target->getId(), 'position' => $position]);
    }
}
