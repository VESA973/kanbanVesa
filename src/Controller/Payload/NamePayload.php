<?php

declare(strict_types=1);

namespace App\Controller\Payload;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Single-field inline forms of the board (new column, column rename, quick task).
 */
final readonly class NamePayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name = '',
    ) {
    }
}
