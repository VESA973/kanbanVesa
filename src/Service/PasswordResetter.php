<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final readonly class PasswordResetter
{
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Always returns a token (a fake one when nothing was sent) so the response
     * never reveals whether an account exists for this email.
     */
    public function sendResetLink(string $email): ResetPasswordToken
    {
        $user = $this->userRepository->findOneByEmail($email);
        if (null === $user) {
            return $this->resetPasswordHelper->generateFakeResetToken();
        }

        try {
            $token = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            return $this->resetPasswordHelper->generateFakeResetToken();
        }

        $this->sendEmail($user, $token);

        return $token;
    }

    /**
     * @throws ResetPasswordExceptionInterface when the token is invalid or expired
     */
    public function findUserByToken(string $token): User
    {
        $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        if (!$user instanceof User) {
            throw new \LogicException('The reset password token must belong to an App\Entity\User.');
        }

        return $user;
    }

    public function resetPassword(string $token, User $user, string $plainPassword): void
    {
        $this->resetPasswordHelper->removeResetRequest($token);

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->userRepository->save($user);
    }

    private function sendEmail(User $user, ResetPasswordToken $token): void
    {
        $this->mailer->send(new TemplatedEmail()
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.reset_password.subject'))
            ->htmlTemplate('email/reset_password.html.twig')
            ->context([
                'firstName' => $user->getFirstName(),
                'token' => $token->getToken(),
                'expiresAtMessageKey' => $token->getExpirationMessageKey(),
                'expiresAtMessageData' => $token->getExpirationMessageData(),
            ]));
    }
}
