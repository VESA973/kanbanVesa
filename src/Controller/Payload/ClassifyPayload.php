<?php

declare(strict_types=1);

namespace App\Controller\Payload;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The pole a program is filed in by the current user (null: none).
 */
final readonly class ClassifyPayload
{
    public function __construct(
        #[Assert\Positive]
        public ?int $poleId = null,
    ) {
    }
}
