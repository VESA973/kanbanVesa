<?php

declare(strict_types=1);

namespace App\Controller\Payload;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON body sent by the drag & drop (sortable_controller.js).
 */
final readonly class MovePayload
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $position,
        #[Assert\Positive]
        public ?int $columnId = null,
    ) {
    }
}
