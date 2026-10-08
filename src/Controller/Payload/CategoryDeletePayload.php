<?php

declare(strict_types=1);

namespace App\Controller\Payload;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Deletion of a category: where its tasks go (required only when it has tasks).
 */
final readonly class CategoryDeletePayload
{
    public function __construct(
        #[Assert\Positive]
        public ?int $targetId = null,
    ) {
    }
}
