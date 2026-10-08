<?php

declare(strict_types=1);

namespace App\Controller\Payload;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * New category and category rename forms of the board.
 */
final readonly class CategoryPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 50)]
        public string $name = '',
    ) {
    }
}
