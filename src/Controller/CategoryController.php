<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Payload\CategoryDeletePayload;
use App\Controller\Payload\CategoryPayload;
use App\Controller\Payload\MovePayload;
use App\Entity\Category;
use App\Entity\Project;
use App\Exception\CategoryException;
use App\Repository\CategoryRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\CategoryManager;
use App\Service\CategoryMover;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryManager $categoryManager,
    ) {
    }

    #[Route('/projects/{id}/categories', name: 'app_category_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, 'project', statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_CATEGORIES, 'project')]
    #[IsCsrfTokenValid(new Expression('"board-" ~ args["project"].getId()'))]
    public function new(Project $project, #[MapRequestPayload] CategoryPayload $payload): Response
    {
        $this->categoryManager->create($project, $payload->name);

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/categories/{id}/rename', name: 'app_category_rename', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, new Expression('args["category"].getProject()'), statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_CATEGORIES, new Expression('args["category"].getProject()'))]
    #[IsCsrfTokenValid(new Expression('"board-" ~ args["category"].getProject().getId()'))]
    public function rename(Category $category, #[MapRequestPayload] CategoryPayload $payload): Response
    {
        $this->categoryManager->rename($category, $payload->name);

        return $this->redirectToRoute('app_project_show', ['id' => $category->getProject()->getId()]);
    }

    #[Route('/categories/{id}/delete', name: 'app_category_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, new Expression('args["category"].getProject()'), statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_CATEGORIES, new Expression('args["category"].getProject()'))]
    #[IsCsrfTokenValid(new Expression('"board-" ~ args["category"].getProject().getId()'))]
    public function delete(Category $category, #[MapRequestPayload] CategoryDeletePayload $payload, CategoryRepository $categoryRepository): Response
    {
        $project = $category->getProject();
        try {
            $this->categoryManager->delete($category, $categoryRepository->findInProject($project, $payload->targetId));
            $this->addFlash('success', 'flash.category.deleted');
        } catch (CategoryException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/categories/{id}/move', name: 'app_category_move', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(ProjectVoter::VIEW, new Expression('args["category"].getProject()'), statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_CATEGORIES, new Expression('args["category"].getProject()'))]
    #[IsCsrfTokenValid(new Expression('"board-" ~ args["category"].getProject().getId()'), tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function move(Category $category, #[MapRequestPayload] MovePayload $payload, CategoryMover $categoryMover): JsonResponse
    {
        return $this->json(['position' => $categoryMover->move($category, $payload->position)]);
    }
}
