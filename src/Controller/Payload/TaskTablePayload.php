<?php

declare(strict_types=1);

namespace App\Controller\Payload;

use App\Enum\TableTemplate;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * New table (title + template) and table rename (title only).
 */
final readonly class TaskTablePayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $title = '',
        public TableTemplate $template = TableTemplate::EMPTY,
    ) {
    }
}
