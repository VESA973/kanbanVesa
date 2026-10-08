<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Program;
use App\Entity\User;
use App\Exception\ProgramException;
use App\Form\Data\ProgramData;
use App\Form\ProjectFormType;
use App\Repository\PoleRepository;
use App\Security\Voter\ProgramVoter;
use App\Service\PoleManager;
use App\Service\ProgramImageStorage;
use App\Service\ProgramManager;
use App\Service\ProjectDirectory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Programs ("Projets" in the interface) group projects ("Chantiers"). Non-members get a 404.
 */
#[Route('/programs')]
final class ProgramController extends AbstractController
{
    public function __construct(
        private readonly ProgramManager $programManager,
    ) {
    }

    #[Route('/new', name: 'app_program_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PoleManager $poleManager, #[CurrentUser] User $user): Response
    {
        $data = new ProgramData();
        $form = $this->createForm(ProjectFormType::class, $data, $this->formOptions() + ['pole_choices_for' => $user])->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('program/new.html.twig', ['form' => $form]);
        }

        $program = $this->programManager->create($data, $user);
        $poleManager->classify($program, $user, $data->pole);
        $this->addFlash('success', 'flash.program.created');

        return $this->redirectToRoute('app_program_show', ['id' => $program->getId()]);
    }

    #[Route('/{id}', name: 'app_program_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ProgramVoter::VIEW, 'program', statusCode: 404)]
    public function show(Program $program, ProjectDirectory $projectDirectory, PoleManager $poleManager, PoleRepository $poleRepository, #[CurrentUser] User $user): Response
    {
        return $this->render('program/show.html.twig', [
            'section' => $projectDirectory->projectsOf($program, $user),
            'poles' => $poleRepository->findForUser($user),
            'currentPole' => $poleManager->poleOf($program, $user),
        ]);
    }

    /**
     * Served by PHP rather than from public/: only people with access to the program see it.
     */
    #[Route('/{id}/image', name: 'app_program_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ProgramVoter::VIEW, 'program', statusCode: 404)]
    public function image(Program $program, ProgramImageStorage $imageStorage): Response
    {
        $path = $imageStorage->pathOf($program);
        if (null === $path || !is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        // The URL changes with each new image (?v=filename), so it can be cached for long.
        $response->setPrivate();
        $response->setMaxAge(2592000);

        return $response;
    }

    #[Route('/{id}/edit', name: 'app_program_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ProgramVoter::VIEW, 'program', statusCode: 404)]
    #[IsGranted(ProgramVoter::EDIT, 'program')]
    public function edit(Request $request, Program $program): Response
    {
        $data = ProgramData::fromProgram($program);
        $form = $this->createForm(ProjectFormType::class, $data, $this->formOptions($program))->handleRequest($request);
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

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?Program $program = null): array
    {
        return [
            'data_class' => ProgramData::class,
            'name_label' => 'program.name',
            'with_image' => true,
            'has_image' => null !== $program?->getImageFilename(),
        ];
    }
}
