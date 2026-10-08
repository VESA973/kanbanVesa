<?php

declare(strict_types=1);

namespace App\Form\Data;

use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Changing one's password requires the current one: a session left open
 * must not be enough to take over the account.
 */
final class AccountPasswordData
{
    #[UserPassword(message: 'account.password.wrong_current')]
    public string $currentPassword = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 4096)]
    public string $plainPassword = '';
}
