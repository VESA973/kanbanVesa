<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Payload\RolePayload;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Exception\InvitationException;
use App\Form\Data\InvitationData;
use App\Form\InvitationFormType;
use App\Repository\InvitationRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\InvitationManager;
use App\Service\MembershipManager;
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

final class MemberController extends AbstractController
{
    public function __construct(
        private readonly InvitationRepository $invitationRepository,
    ) {
    }

    #[Route('/projects/{id}/members', name: 'app_project_members', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ProjectVoter::VIEW, 'project', statusCode: 404)]
    public function index(Project $project): Response
    {
        return $this->renderMembers($project, $this->createForm(InvitationFormType::class, new InvitationData()));
    }

    #[Route('/projects/{id}/invitations', name: 'app_invitation_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, 'project', statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_MEMBERS, 'project')]
    public function invite(Request $request, Project $project, InvitationManager $invitationManager, TranslatorInterface $translator, #[CurrentUser] User $user): Response
    {
        $data = new InvitationData();
        $form = $this->createForm(InvitationFormType::class, $data)->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderMembers($project, $form);
        }

        try {
            $invitationManager->invite($project, $data->email, $data->role, $user);
        } catch (InvitationException $exception) {
            $form->get('email')->addError(new FormError($translator->trans($exception->getMessage())));

            return $this->renderMembers($project, $form);
        }

        $this->addFlash('success', 'flash.invitation.sent');

        return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
    }

    #[Route('/members/{id}/role', name: 'app_member_role', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, new Expression('args["member"].getProject()'), statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_MEMBERS, new Expression('args["member"].getProject()'))]
    #[IsCsrfTokenValid(new Expression('"members-" ~ args["member"].getProject().getId()'))]
    public function changeRole(ProjectMember $member, #[MapRequestPayload] RolePayload $payload, MembershipManager $membershipManager): Response
    {
        $this->denyIfNotManagedHere($member);
        $membershipManager->changeRole($member, $payload->role);
        $this->addFlash('success', 'flash.member.role_changed');

        return $this->redirectToRoute('app_project_members', ['id' => $member->getProject()->getId()]);
    }

    #[Route('/members/{id}/remove', name: 'app_member_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, new Expression('args["member"].getProject()'), statusCode: 404)]
    #[IsGranted(ProjectVoter::MANAGE_MEMBERS, new Expression('args["member"].getProject()'))]
    #[IsCsrfTokenValid(new Expression('"members-" ~ args["member"].getProject().getId()'))]
    public function remove(ProjectMember $member, MembershipManager $membershipManager): Response
    {
        $this->denyIfNotManagedHere($member);
        $membershipManager->remove($member);
        $this->addFlash('success', 'flash.member.removed');

        return $this->redirectToRoute('app_project_members', ['id' => $member->getProject()->getId()]);
    }

    /**
     * @param FormInterface<InvitationData> $form
     */
    private function renderMembers(Project $project, FormInterface $form): Response
    {
        return $this->render('project/members.html.twig', [
            'project' => $project,
            'form' => $form,
            'invitations' => $this->invitationRepository->findAllFor($project),
        ]);
    }

    /**
     * The owner is never removed nor demoted: the UI hides these actions, this rejects forged requests.
     */
    private function denyIfNotManagedHere(ProjectMember $member): void
    {
        if (ProjectRole::OWNER === $member->getRole()) {
            throw $this->createAccessDeniedException('The owner cannot be removed nor demoted.');
        }
        if ($member->isInherited()) {
            throw $this->createAccessDeniedException('This access comes from the program: it is managed on the program members page.');
        }
    }
}
