<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Payload\RolePayload;
use App\Entity\Program;
use App\Entity\ProgramMember;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Exception\InvitationException;
use App\Form\Data\InvitationData;
use App\Form\InvitationFormType;
use App\Repository\InvitationRepository;
use App\Security\Voter\ProgramVoter;
use App\Service\InvitationManager;
use App\Service\ProgramMembership;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Members of a program: they get the same role on all its projects (see ProgramAccess).
 */
final class ProgramMemberController extends AbstractController
{
    public function __construct(
        private readonly InvitationRepository $invitationRepository,
        private readonly ProgramMembership $programMembership,
    ) {
    }

    #[Route('/programs/{id}/members', name: 'app_program_members', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ProgramVoter::VIEW, 'program', statusCode: 404)]
    public function index(Program $program): Response
    {
        return $this->renderMembers($program, $this->createForm(InvitationFormType::class, new InvitationData()));
    }

    #[Route('/programs/{id}/invitations', name: 'app_program_invitation_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProgramVoter::VIEW, 'program', statusCode: 404)]
    #[IsGranted(ProgramVoter::MANAGE_MEMBERS, 'program')]
    public function invite(Request $request, Program $program, InvitationManager $invitationManager, TranslatorInterface $translator, #[CurrentUser] User $user): Response
    {
        $data = new InvitationData();
        $form = $this->createForm(InvitationFormType::class, $data)->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderMembers($program, $form);
        }

        try {
            $invitationManager->invite($program, $data->email, $data->role, $user);
        } catch (InvitationException $exception) {
            $form->get('email')->addError(new FormError($translator->trans($exception->getMessage())));

            return $this->renderMembers($program, $form);
        }
        $this->addFlash('success', 'flash.invitation.sent');

        return $this->redirectToRoute('app_program_members', ['id' => $program->getId()]);
    }

    #[Route('/program-members/{id}/role', name: 'app_program_member_role', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProgramVoter::VIEW, new Expression('args["member"].getProgram()'), statusCode: 404)]
    #[IsGranted(ProgramVoter::MANAGE_MEMBERS, new Expression('args["member"].getProgram()'))]
    #[IsCsrfTokenValid(new Expression('"program-members-" ~ args["member"].getProgram().getId()'))]
    public function changeRole(ProgramMember $member, #[MapRequestPayload] RolePayload $payload): Response
    {
        $this->denyIfOwner($member);
        $this->programMembership->changeRole($member, $payload->role);
        $this->addFlash('success', 'flash.member.role_changed');

        return $this->redirectToRoute('app_program_members', ['id' => $member->getProgram()->getId()]);
    }

    #[Route('/program-members/{id}/remove', name: 'app_program_member_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProgramVoter::VIEW, new Expression('args["member"].getProgram()'), statusCode: 404)]
    #[IsGranted(ProgramVoter::MANAGE_MEMBERS, new Expression('args["member"].getProgram()'))]
    #[IsCsrfTokenValid(new Expression('"program-members-" ~ args["member"].getProgram().getId()'))]
    public function remove(ProgramMember $member): Response
    {
        $this->denyIfOwner($member);
        $programId = $member->getProgram()->getId();
        $this->programMembership->remove($member);
        $this->addFlash('success', 'flash.program.member_removed');

        return $this->redirectToRoute('app_program_members', ['id' => $programId]);
    }

    /**
     * @param FormInterface<InvitationData> $form
     */
    private function renderMembers(Program $program, FormInterface $form): Response
    {
        return $this->render('program/members.html.twig', [
            'program' => $program,
            'form' => $form,
            'invitations' => $this->invitationRepository->findAllFor($program),
        ]);
    }

    /**
     * The owner is never removed nor demoted: the UI hides these actions, this rejects forged requests.
     */
    private function denyIfOwner(ProgramMember $member): void
    {
        if (ProjectRole::OWNER === $member->getRole()) {
            throw $this->createAccessDeniedException('The owner cannot be removed nor demoted.');
        }
    }
}
