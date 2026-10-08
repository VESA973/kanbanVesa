<?php

declare(strict_types=1);

namespace App\Controller\Payload;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ColumnStepPayload
{
    /**
     * @param -1|1 $step -1: one place to the left, 1: to the right (enforced by the Choice constraint)
     */
    public function __construct(
        #[Assert\Choice(choices: [-1, 1])]
        public int $step = 1,
    ) {
    }
}
