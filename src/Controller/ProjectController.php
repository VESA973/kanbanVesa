<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BoardColumn;
use App\Entity\Project;
use App\Entity\User;
use App\Form\Data\ProjectData;
use App\Form\ProjectFormType;
use App\Model\BoardFilter;
use App\Repository\BoardColumnRepository;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\ProjectCreator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Non-members get a 404 (statusCode on the VIEW check) so the existence
 * of a project is never revealed to them.
 */
#[Route('/projects')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
    ) {
    }

    #[Route('', name: 'app_project_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('project/index.html.twig', [
            'projects' => $this->projectRepository->findActiveForMember($user),
        ]);
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ProjectCreator $projectCreator, #[CurrentUser] User $user): Response
    {
        $data = new ProjectData();
        $form = $this->createForm(ProjectFormType::class, $data);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('project/new.html.twig', ['form' => $form]);
        }

        $project = $projectCreator->create($data, $user);
        $this->addFlash('success', 'flash.project.created');

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/{id}', name: 'app_project_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ProjectVoter::VIEW, 'project', statusCode: 404)]
    public function show(Project $project, BoardColumnRepository $columnRepository, TaskRepository $taskRepository, #[MapQueryString] BoardFilter $filter = new BoardFilter()): Response
    {
        $columns = $columnRepository->findBoard($project);
        $tasks = array_merge(...array_map(static fn (BoardColumn $column): array => $column->getTasks()->getValues(), $columns));
        $taskRepository->preloadCardDetails($tasks);

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'columns' => $columns,
            'filter' => $filter,
            'commentCounts' => $taskRepository->countCommentsByTask($tasks),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_project_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ProjectVoter::VIEW, 'project', statusCode: 404)]
    #[IsGranted(ProjectVoter::EDIT, 'project')]
    public function edit(Request $request, Project $project): Response
    {
        $data = ProjectData::fromProject($project);
        $form = $this->createForm(ProjectFormType::class, $data);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('project/edit.html.twig', ['project' => $project, 'form' => $form]);
        }

        $project->update($data->name, $data->description, $data->color);
        $this->projectRepository->save($project);
        $this->addFlash('success', 'flash.project.updated');

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_project_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, 'project', statusCode: 404)]
    #[IsGranted(ProjectVoter::DELETE, 'project')]
    #[IsCsrfTokenValid(new Expression('"delete-project-" ~ args["project"].getId()'))]
    public function delete(Project $project): Response
    {
        $this->projectRepository->remove($project);
        $this->addFlash('success', 'flash.project.deleted');

        return $this->redirectToRoute('app_project_index');
    }
}
