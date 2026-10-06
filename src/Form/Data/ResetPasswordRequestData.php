<?php

declare(strict_types=1);

namespace App\Form\Data;

use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordRequestData
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';
}
