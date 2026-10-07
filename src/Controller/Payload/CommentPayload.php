<?php

declare(strict_types=1);

namespace App\Controller\Payload;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CommentPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 5000)]
        public string $content = '',
    ) {
    }
}
