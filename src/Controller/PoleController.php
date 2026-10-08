<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Payload\ClassifyPayload;
use App\Controller\Payload\PolePayload;
use App\Controller\Payload\StepPayload;
use App\Entity\Pole;
use App\Entity\Program;
use App\Entity\User;
use App\Repository\PoleRepository;
use App\Repository\ProgramRepository;
use App\Security\Voter\PoleVoter;
use App\Security\Voter\ProgramVoter;
use App\Service\PoleManager;
use App\Service\ProjectDirectory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Personal poles used to sort one's programs on "Mes projets".
 */
final class PoleController extends AbstractController
{
    public function __construct(
        private readonly PoleManager $poleManager,
    ) {
    }

    #[Route('/poles', name: 'app_pole_index', methods: ['GET'])]
    public function index(PoleRepository $poleRepository, #[CurrentUser] User $user): Response
    {
        return $this->render('pole/index.html.twig', ['poles' => $poleRepository->findForUser($user)]);
    }

    #[Route('/poles', name: 'app_pole_new', methods: ['POST'])]
    #[IsCsrfTokenValid('poles')]
    public function new(Request $request, #[MapRequestPayload] PolePayload $payload, #[CurrentUser] User $user): Response
    {
        $pole = $this->poleManager->create($user, $payload->name);

        // Created from "Mes projets": go straight to the new pole to fill it.
        return $request->request->getBoolean('_open')
            ? $this->redirectToRoute('app_pole_show', ['id' => $pole->getId()])
            : $this->redirectToRoute('app_pole_index');
    }

    #[Route('/poles/{id}', name: 'app_pole_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PoleVoter::MANAGE, 'pole', statusCode: 404)]
    public function show(Pole $pole, ProjectDirectory $projectDirectory, PoleRepository $poleRepository, #[CurrentUser] User $user): Response
    {
        return $this->render('pole/show.html.twig', $projectDirectory->poleView($user, $pole) + ['poles' => $poleRepository->findForUser($user)]);
    }

    #[Route('/poles/none', name: 'app_pole_unfiled', methods: ['GET'])]
    public function unfiled(ProjectDirectory $projectDirectory, PoleRepository $poleRepository, #[CurrentUser] User $user): Response
    {
        return $this->render('pole/show.html.twig', $projectDirectory->poleView($user, null) + ['poles' => $poleRepository->findForUser($user)]);
    }

    /**
     * "Ajouter des projets à ce pôle": several programs at once (ignores those the user cannot see).
     */
    #[Route('/poles/{id}/programs', name: 'app_pole_add_programs', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PoleVoter::MANAGE, 'pole', statusCode: 404)]
    #[IsCsrfTokenValid('poles')]
    public function addPrograms(Request $request, Pole $pole, ProgramRepository $programRepository, #[CurrentUser] User $user): Response
    {
        $ids = array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $request->request->all('programIds'));
        foreach ($programRepository->findBy(['id' => $ids]) as $program) {
            if ($this->isGranted(ProgramVoter::VIEW, $program)) {
                $this->poleManager->classify($program, $user, $pole);
            }
        }

        return $this->redirectToRoute('app_pole_show', ['id' => $pole->getId()]);
    }

    #[Route('/poles/{id}/rename', name: 'app_pole_rename', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PoleVoter::MANAGE, 'pole', statusCode: 404)]
    #[IsCsrfTokenValid('poles')]
    public function rename(Pole $pole, #[MapRequestPayload] PolePayload $payload): Response
    {
        $this->poleManager->rename($pole, $payload->name);

        return $this->redirectToRoute('app_pole_index');
    }

    #[Route('/poles/{id}/move', name: 'app_pole_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PoleVoter::MANAGE, 'pole', statusCode: 404)]
    #[IsCsrfTokenValid('poles')]
    public function move(Pole $pole, #[MapRequestPayload] StepPayload $payload): Response
    {
        $this->poleManager->move($pole, $payload->step);

        return $this->redirectToRoute('app_pole_index');
    }

    #[Route('/poles/{id}/delete', name: 'app_pole_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PoleVoter::MANAGE, 'pole', statusCode: 404)]
    #[IsCsrfTokenValid('poles')]
    public function delete(Pole $pole): Response
    {
        $this->poleManager->delete($pole);
        $this->addFlash('success', 'flash.pole.deleted');

        return $this->redirectToRoute('app_pole_index');
    }

    /**
     * Files the program in one of the current user's poles (or none).
     */
    #[Route('/programs/{id}/pole', name: 'app_program_classify', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProgramVoter::VIEW, 'program', statusCode: 404)]
    #[IsCsrfTokenValid(new Expression('"classify-program-" ~ args["program"].getId()'))]
    public function classify(Request $request, Program $program, #[MapRequestPayload] ClassifyPayload $payload, PoleRepository $poleRepository, #[CurrentUser] User $user): Response
    {
        $pole = null === $payload->poleId ? null : $poleRepository->find($payload->poleId);
        if (null !== $payload->poleId && (null === $pole || !$this->isGranted(PoleVoter::MANAGE, $pole))) {
            throw $this->createNotFoundException();
        }

        $this->poleManager->classify($program, $user, $pole);
        $this->addFlash('success', 'flash.pole.classified');

        // Only local paths: "//host" or "/\host" would be followed to another site.
        $returnTo = $request->request->getString('_return_to');

        return 1 === preg_match('#^/(?![/\\\\])#', $returnTo)
            ? $this->redirect($returnTo)
            : $this->redirectToRoute('app_program_show', ['id' => $program->getId()]);
    }
}
