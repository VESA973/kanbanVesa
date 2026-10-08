<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Form\Data\AccountProfileData;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * What users change themselves on "Mon compte" (roles are changed by administrators only).
 */
final readonly class AccountManager
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function updateProfile(User $user, AccountProfileData $data): void
    {
        $user->rename($data->firstName, $data->lastName);
        $this->userRepository->save($user);
    }

    /**
     * The current password has already been checked by the form (UserPassword constraint).
     */
    public function changePassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->userRepository->save($user);
    }
}
