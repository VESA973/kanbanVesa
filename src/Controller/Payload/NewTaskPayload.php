<?php

declare(strict_types=1);

namespace App\Controller\Payload;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Quick task form at the bottom of a board cell (a column within a category).
 */
final readonly class NewTaskPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name = '',
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $categoryId = null,
    ) {
    }
}
