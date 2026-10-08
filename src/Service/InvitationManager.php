<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\Program;
use App\Entity\Project;
use App\Entity\Task;
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
        private ProgramMembership $programMembership,
    ) {
    }

    /**
     * @param Project|Program $target a program gives access to all its projects
     * @param Task|null       $task   assigned to the invitee when they accept (project invitations only)
     *
     * @throws InvitationException when the address already belongs to a member
     */
    public function invite(Project|Program $target, string $email, ProjectRole $role, User $invitedBy, ?Task $task = null): Invitation
    {
        if ($this->isMember($target, $email)) {
            throw InvitationException::alreadyMember();
        }

        $plainToken = bin2hex(random_bytes(32));
        $invitation = $this->invitationRepository->findNotAcceptedFor($target, $email);
        if (null === $invitation) {
            $invitation = new Invitation($target, $email, $role, $invitedBy, $plainToken);
        } else {
            $invitation->renew($role, $plainToken);
        }
        if (null !== $task) {
            $invitation->forTask($task);
        }

        $this->send($invitation, $plainToken);

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
     * @return Project|Program what the user just joined
     *
     * @throws InvitationException
     */
    public function accept(string $plainToken, User $user): Project|Program
    {
        $invitation = $this->findValid($plainToken);
        if ($invitation->getEmail() !== $user->getEmail()) {
            throw InvitationException::emailMismatch();
        }

        // Only a link received by e-mail proves the address belongs to the user.
        if (!$invitation->isLinkShared()) {
            $user->markAsVerified();
        }
        $invitation->markAsAccepted();

        $target = $invitation->getTarget();
        if ($target instanceof Program) {
            $this->programMembership->join($target, $user, $invitation->getRole());
            $this->invitationRepository->save($invitation);

            return $target;
        }

        if (null === $target->getRoleOf($user)) {
            $target->addMember($user, $invitation->getRole());
        }
        $this->assignInvitedTask($invitation, $user);
        $this->dispatcher->dispatch(new ProjectActivityEvent($target, ActivityAction::MEMBER_JOINED, $user->getFullName(), [
            'role' => $this->translator->trans($invitation->getRole()->translationKey()),
        ]));
        $this->invitationRepository->save($invitation);

        return $target;
    }

    /**
     * @return string the new plain token, to build the link shown once to the owner
     */
    public function createShareableLink(Invitation $invitation): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $invitation->shareLink($plainToken);
        $this->invitationRepository->save($invitation);

        return $plainToken;
    }

    /**
     * Sends a fresh e-mail with a new token valid for another Invitation::LIFETIME; previous links stop working.
     *
     * @throws InvitationException when the invitation was already accepted
     */
    public function resend(Invitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw InvitationException::alreadyUsed();
        }

        $plainToken = bin2hex(random_bytes(32));
        $invitation->renew($invitation->getRole(), $plainToken);
        $this->send($invitation, $plainToken);
    }

    /**
     * @throws InvitationException when the invitation was already accepted (it is kept as a record)
     */
    public function revoke(Invitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw InvitationException::alreadyUsed();
        }

        $this->invitationRepository->remove($invitation);
    }

    private function send(Invitation $invitation, string $plainToken): void
    {
        // The activity log belongs to projects; program invitations stay listed on the program members page.
        $project = $invitation->getProject();
        if (null !== $project) {
            $this->dispatcher->dispatch(new ProjectActivityEvent($project, ActivityAction::INVITATION_SENT, $invitation->getEmail(), [
                'role' => $this->translator->trans($invitation->getRole()->translationKey()),
            ]));
        }
        $this->invitationRepository->save($invitation);
        $this->sendEmail($invitation, $plainToken);
    }

    private function assignInvitedTask(Invitation $invitation, User $user): void
    {
        $task = $invitation->getTask();
        if (null === $task) {
            return;
        }

        $task->assignTo($user);
        $this->dispatcher->dispatch(new ProjectActivityEvent($task->getProject(), ActivityAction::TASK_ASSIGNED, $task->getTitle(), [
            'assignee' => $user->getFullName(),
        ], $task));
    }

    private function isMember(Project|Program $target, string $email): bool
    {
        $email = mb_strtolower(trim($email));
        foreach ($target->getMembers() as $member) {
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
            // A real person to answer to, rather than the no-reply sender.
            ->replyTo($invitation->getInvitedBy()->getEmail())
            ->subject($this->translator->trans('email.invitation.subject', ['%project%' => $invitation->getTarget()->getName()]))
            ->htmlTemplate('email/invitation.html.twig')
            ->context([
                'inviterName' => $invitation->getInvitedBy()->getFullName(),
                'projectName' => $invitation->getTarget()->getName(),
                'isProgram' => $invitation->getTarget() instanceof Program,
                'roleKey' => $invitation->getRole()->translationKey(),
                'token' => $plainToken,
                'expiresAt' => $invitation->getExpiresAt(),
            ]));
    }
}
