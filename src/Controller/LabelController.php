<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Label;
use App\Entity\Project;
use App\Form\Data\LabelData;
use App\Form\LabelFormType;
use App\Security\Voter\ProjectVoter;
use App\Service\LabelManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class LabelController extends AbstractController
{
    #[Route('/projects/{id}/labels', name: 'app_project_labels', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ProjectVoter::VIEW, 'project', statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_LABELS, 'project')]
    public function index(Request $request, Project $project, LabelManager $labelManager): Response
    {
        $data = new LabelData($project);
        $form = $this->createForm(LabelFormType::class, $data)->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('project/labels.html.twig', ['project' => $project, 'form' => $form]);
        }

        $labelManager->create($project, $data->name, $data->color);
        $this->addFlash('success', 'flash.label.created');

        return $this->redirectToRoute('app_project_labels', ['id' => $project->getId()]);
    }

    #[Route('/labels/{id}/delete', name: 'app_label_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, new Expression('args["label"].getProject()'), statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_LABELS, new Expression('args["label"].getProject()'))]
    #[IsCsrfTokenValid(new Expression('"labels-" ~ args["label"].getProject().getId()'))]
    public function delete(Label $label, LabelManager $labelManager): Response
    {
        $projectId = $label->getProject()->getId();
        $labelManager->delete($label);
        $this->addFlash('success', 'flash.label.deleted');

        return $this->redirectToRoute('app_project_labels', ['id' => $projectId]);
    }
}
