<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Program;
use App\Entity\User;
use App\Exception\InvitationException;
use App\Service\InvitationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

#[Route('/invitations/{token}', requirements: ['token' => '[a-f0-9]{64}'])]
final class InvitationController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(
        private readonly InvitationManager $invitationManager,
    ) {
    }

    /**
     * Public page: a visitor who logs in or registers from here comes back to it afterwards.
     */
    #[Route('', name: 'app_invitation_show', methods: ['GET'])]
    public function show(Request $request, string $token): Response
    {
        if (null === $this->getUser()) {
            $this->saveTargetPath($request->getSession(), 'main', $request->getUri());
        }

        try {
            $invitation = $this->invitationManager->findValid($token);
        } catch (InvitationException $exception) {
            return $this->render('invitation/show.html.twig', ['error' => $exception->getMessage()], new Response(status: Response::HTTP_GONE));
        }

        return $this->render('invitation/show.html.twig', ['invitation' => $invitation, 'token' => $token]);
    }

    #[Route('/accept', name: 'app_invitation_accept', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[IsCsrfTokenValid('accept-invitation')]
    public function accept(string $token, #[CurrentUser] User $user): Response
    {
        try {
            $invitation = $this->invitationManager->findValid($token);
            $joined = $this->invitationManager->accept($token, $user);
        } catch (InvitationException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_invitation_show', ['token' => $token]);
        }

        $this->addFlash('success', 'flash.invitation.accepted');

        // Invited for a task: "Mes tâches" shows it straight away.
        return match (true) {
            null !== $invitation->getTask() => $this->redirectToRoute('app_my_tasks'),
            $joined instanceof Program => $this->redirectToRoute('app_program_show', ['id' => $joined->getId()]),
            default => $this->redirectToRoute('app_project_show', ['id' => $joined->getId()]),
        };
    }
}
