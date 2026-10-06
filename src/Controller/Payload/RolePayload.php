<?php

declare(strict_types=1);

namespace App\Controller\Payload;

use App\Enum\ProjectRole;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RolePayload
{
    public function __construct(
        #[Assert\Choice(choices: [ProjectRole::EDITOR, ProjectRole::VIEWER])]
        public ProjectRole $role,
    ) {
    }
}
