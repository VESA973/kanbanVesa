<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\Project;
use App\Exception\InvitationException;
use App\Security\Voter\InvitationVoter;
use App\Service\InvitationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Actions on a sent invitation, from the members page of its project or program.
 */
#[Route('/invitations/{id}', requirements: ['id' => '\d+'], methods: ['POST'])]
#[IsGranted(InvitationVoter::VIEW, 'invitation', statusCode: 404)]
#[IsGranted(InvitationVoter::MANAGE, 'invitation')]
#[IsCsrfTokenValid(new Expression('args["invitation"].getMembersTokenId()'))]
final class ManagedInvitationController extends AbstractController
{
    public function __construct(
        private readonly InvitationManager $invitationManager,
    ) {
    }

    #[Route('/revoke', name: 'app_invitation_revoke')]
    public function revoke(Invitation $invitation): Response
    {
        $response = $this->backToMembers($invitation);
        try {
            $this->invitationManager->revoke($invitation);
            $this->addFlash('success', 'flash.invitation.revoked');
        } catch (InvitationException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $response;
    }

    #[Route('/resend', name: 'app_invitation_resend')]
    public function resend(Invitation $invitation): Response
    {
        try {
            $this->invitationManager->resend($invitation);
            $this->addFlash('success', 'flash.invitation.resent');
        } catch (InvitationException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->backToMembers($invitation);
    }

    /**
     * Shows, once, a link the owner can share by hand (WhatsApp, SMS…) when e-mail is not an option.
     */
    #[Route('/link', name: 'app_invitation_link')]
    public function shareLink(Invitation $invitation): Response
    {
        $token = $this->invitationManager->createShareableLink($invitation);

        return $this->render('invitation/link.html.twig', [
            'invitation' => $invitation,
            'link' => $this->generateUrl('app_invitation_show', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    private function backToMembers(Invitation $invitation): Response
    {
        $target = $invitation->getTarget();

        return $this->redirectToRoute($target instanceof Project ? 'app_project_members' : 'app_program_members', ['id' => $target->getId()]);
    }
}
