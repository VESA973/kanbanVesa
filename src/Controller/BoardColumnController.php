<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Payload\MovePayload;
use App\Controller\Payload\NamePayload;
use App\Entity\BoardColumn;
use App\Entity\Project;
use App\Repository\BoardColumnRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\ColumnMover;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class BoardColumnController extends AbstractController
{
    public function __construct(
        private readonly BoardColumnRepository $columnRepository,
    ) {
    }

    #[Route('/projects/{id}/columns', name: 'app_column_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, 'project', statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_COLUMNS, 'project')]
    #[IsCsrfTokenValid(new Expression('"board-" ~ args["project"].getId()'))]
    public function new(Project $project, #[MapRequestPayload] NamePayload $payload): Response
    {
        $this->columnRepository->save($project->addColumn($payload->name));

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/columns/{id}/rename', name: 'app_column_rename', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, new Expression('args["column"].getProject()'), statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_COLUMNS, new Expression('args["column"].getProject()'))]
    #[IsCsrfTokenValid(new Expression('"board-" ~ args["column"].getProject().getId()'))]
    public function rename(BoardColumn $column, #[MapRequestPayload] NamePayload $payload): Response
    {
        $column->rename($payload->name);
        $this->columnRepository->save($column);

        return $this->redirectToRoute('app_project_show', ['id' => $column->getProject()->getId()]);
    }

    #[Route('/columns/{id}/delete', name: 'app_column_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, new Expression('args["column"].getProject()'), statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_COLUMNS, new Expression('args["column"].getProject()'))]
    #[IsCsrfTokenValid(new Expression('"board-" ~ args["column"].getProject().getId()'))]
    public function delete(BoardColumn $column): Response
    {
        $projectId = $column->getProject()->getId();
        $this->columnRepository->remove($column);
        $this->addFlash('success', 'flash.column.deleted');

        return $this->redirectToRoute('app_project_show', ['id' => $projectId]);
    }

    #[Route('/columns/{id}/move', name: 'app_column_move', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(ProjectVoter::VIEW, new Expression('args["column"].getProject()'), statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_COLUMNS, new Expression('args["column"].getProject()'))]
    #[IsCsrfTokenValid(new Expression('"board-" ~ args["column"].getProject().getId()'), tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function move(BoardColumn $column, #[MapRequestPayload] MovePayload $payload, ColumnMover $columnMover): JsonResponse
    {
        return $this->json(['position' => $columnMover->move($column, $payload->position)]);
    }
}
