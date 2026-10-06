<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Enum\ProjectRole;
use Symfony\Component\Validator\Constraints as Assert;

final class InvitationData
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\Choice(choices: [ProjectRole::EDITOR, ProjectRole::VIEWER])]
    public ProjectRole $role = ProjectRole::EDITOR;
}
