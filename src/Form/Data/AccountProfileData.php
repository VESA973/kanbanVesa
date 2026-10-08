<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final class AccountProfileData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $firstName = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $lastName = '';

    public static function fromUser(User $user): self
    {
        $data = new self();
        $data->firstName = $user->getFirstName();
        $data->lastName = $user->getLastName();

        return $data;
    }
}
