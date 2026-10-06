<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Enum\ProjectRole;
use App\Event\ProjectActivityEvent;
use App\Exception\InvitationException;
use App\Repository\InvitationRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class InvitationManager
{
    public function __construct(
        private InvitationRepository $invitationRepository,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws InvitationException when the address already belongs to a member
     */
    public function invite(Project $project, string $email, ProjectRole $role, User $invitedBy): Invitation
    {
        if ($this->isMember($project, $email)) {
            throw InvitationException::alreadyMember();
        }

        $plainToken = bin2hex(random_bytes(32));
        $invitation = $this->invitationRepository->findNotAcceptedFor($project, $email);
        if (null === $invitation) {
            $invitation = new Invitation($project, $email, $role, $invitedBy, $plainToken);
        } else {
            $invitation->renew($role, $plainToken);
        }

        $this->dispatcher->dispatch(new ProjectActivityEvent($project, ActivityAction::INVITATION_SENT, $invitation->getEmail(), [
            'role' => $this->translator->trans($role->translationKey()),
        ]));
        $this->invitationRepository->save($invitation);
        $this->sendEmail($invitation, $plainToken);

        return $invitation;
    }

    /**
     * @throws InvitationException when the token is unknown, already used or expired
     */
    public function findValid(string $plainToken): Invitation
    {
        $invitation = $this->invitationRepository->findOneByPlainToken($plainToken) ?? throw InvitationException::notFound();

        return match (true) {
            $invitation->isAccepted() => throw InvitationException::alreadyUsed(),
            $invitation->isExpired() => throw InvitationException::expired(),
            default => $invitation,
        };
    }

    /**
     * Receiving the link proves the address belongs to the user, so the account
     * is marked as verified at the same time.
     *
     * @throws InvitationException
     */
    public function accept(string $plainToken, User $user): Project
    {
        $invitation = $this->findValid($plainToken);
        if ($invitation->getEmail() !== $user->getEmail()) {
            throw InvitationException::emailMismatch();
        }

        $project = $invitation->getProject();
        if (null === $project->getRoleOf($user)) {
            $project->addMember($user, $invitation->getRole());
        }

        $user->markAsVerified();
        $invitation->markAsAccepted();
        $this->dispatcher->dispatch(new ProjectActivityEvent($project, ActivityAction::MEMBER_JOINED, $user->getFullName(), [
            'role' => $this->translator->trans($invitation->getRole()->translationKey()),
        ]));
        $this->invitationRepository->save($invitation);

        return $project;
    }

    public function revoke(Invitation $invitation): void
    {
        $this->invitationRepository->remove($invitation);
    }

    private function isMember(Project $project, string $email): bool
    {
        $email = mb_strtolower(trim($email));
        foreach ($project->getMembers() as $member) {
            if ($member->getUser()->getEmail() === $email) {
                return true;
            }
        }

        return false;
    }

    private function sendEmail(Invitation $invitation, string $plainToken): void
    {
        $this->mailer->send(new TemplatedEmail()
            ->to($invitation->getEmail())
            ->subject($this->translator->trans('email.invitation.subject', ['%project%' => $invitation->getProject()->getName()]))
            ->htmlTemplate('email/invitation.html.twig')
            ->context([
                'inviterName' => $invitation->getInvitedBy()->getFullName(),
                'projectName' => $invitation->getProject()->getName(),
                'roleKey' => $invitation->getRole()->translationKey(),
                'token' => $plainToken,
                'expiresAt' => $invitation->getExpiresAt(),
            ]));
    }
}
