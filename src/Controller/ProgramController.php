<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Program;
use App\Entity\User;
use App\Exception\ProgramException;
use App\Form\Data\ProgramData;
use App\Form\ProjectFormType;
use App\Security\Voter\ProgramVoter;
use App\Service\ProgramManager;
use App\Service\ProjectDirectory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Projets globaux": a program groups projects. Non-members get a 404.
 */
#[Route('/programs')]
final class ProgramController extends AbstractController
{
    public function __construct(
        private readonly ProgramManager $programManager,
    ) {
    }

    #[Route('/new', name: 'app_program_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $data = new ProgramData();
        $form = $this->createForm(ProjectFormType::class, $data, ['data_class' => ProgramData::class, 'name_label' => 'program.name'])->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('program/new.html.twig', ['form' => $form]);
        }

        $program = $this->programManager->create($data, $user);
        $this->addFlash('success', 'flash.program.created');

        return $this->redirectToRoute('app_program_show', ['id' => $program->getId()]);
    }

    #[Route('/{id}', name: 'app_program_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ProgramVoter::VIEW, 'program', statusCode: 404)]
    public function show(Program $program, ProjectDirectory $projectDirectory): Response
    {
        return $this->render('program/show.html.twig', ['section' => $projectDirectory->projectsOf($program)]);
    }

    #[Route('/{id}/edit', name: 'app_program_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ProgramVoter::VIEW, 'program', statusCode: 404)]
    #[IsGranted(ProgramVoter::EDIT, 'program')]
    public function edit(Request $request, Program $program): Response
    {
        $data = ProgramData::fromProgram($program);
        $form = $this->createForm(ProjectFormType::class, $data, ['data_class' => ProgramData::class, 'name_label' => 'program.name'])->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('program/edit.html.twig', ['program' => $program, 'form' => $form]);
        }

        $this->programManager->update($program, $data);
        $this->addFlash('success', 'flash.program.updated');

        return $this->redirectToRoute('app_program_show', ['id' => $program->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_program_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProgramVoter::VIEW, 'program', statusCode: 404)]
    #[IsGranted(ProgramVoter::DELETE, 'program')]
    #[IsCsrfTokenValid(new Expression('"delete-program-" ~ args["program"].getId()'))]
    public function delete(Program $program): Response
    {
        try {
            $this->programManager->delete($program);
        } catch (ProgramException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_program_edit', ['id' => $program->getId()]);
        }
        $this->addFlash('success', 'flash.program.deleted');

        return $this->redirectToRoute('app_project_index');
    }
}
