<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Form\BulkAssignFormType;
use App\Form\Data\BulkAssignData;
use App\Security\Voter\ProjectVoter;
use App\Service\TaskBulkAssigner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class BulkAssignController extends AbstractController
{
    #[Route('/projects/{id}/bulk-assign', name: 'app_project_bulk_assign', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ProjectVoter::VIEW, 'project', statusCode: 404)]
    #[IsGranted(ProjectVoter::ASSIGN_TASKS, 'project')]
    public function __invoke(Request $request, Project $project, TaskBulkAssigner $bulkAssigner): Response
    {
        $data = new BulkAssignData();
        $form = $this->createForm(BulkAssignFormType::class, $data, ['project' => $project])->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('project/bulk_assign.html.twig', ['project' => $project, 'form' => $form]);
        }

        $count = $bulkAssigner->assign($project, $data->assignees, $data->column);
        $this->addFlash('success', 0 === $count ? 'flash.bulk_assign.nothing' : 'flash.bulk_assign.done');

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }
}
