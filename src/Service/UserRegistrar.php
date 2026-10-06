<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Form\Data\RegistrationData;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserRegistrar
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerifier $emailVerifier,
    ) {
    }

    public function register(RegistrationData $data): User
    {
        $user = new User($data->email, $data->firstName, $data->lastName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data->plainPassword));
        $this->userRepository->save($user);

        $this->emailVerifier->sendVerificationEmail($user);

        return $user;
    }
}
