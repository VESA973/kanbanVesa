<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final readonly class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private UserRepository $userRepository,
    ) {
    }

    public function sendVerificationEmail(User $user): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            'app_verify_email',
            $this->userId($user),
            $user->getEmail(),
            ['id' => $user->getId()],
        );

        $this->mailer->send((new TemplatedEmail())
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.verify.subject'))
            ->htmlTemplate('email/verify_email.html.twig')
            ->context([
                'firstName' => $user->getFirstName(),
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAtMessageKey' => $signature->getExpirationMessageKey(),
                'expiresAtMessageData' => $signature->getExpirationMessageData(),
            ]));
    }

    /**
     * @throws VerifyEmailExceptionInterface when the link is invalid or expired
     */
    public function confirm(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, $this->userId($user), $user->getEmail());

        $user->markAsVerified();
        $this->userRepository->save($user);
    }

    private function userId(User $user): string
    {
        return (string) ($user->getId() ?? throw new \LogicException('The user must be persisted before verifying its email.'));
    }
}
