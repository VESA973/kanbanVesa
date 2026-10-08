<?php

declare(strict_types=1);

namespace App\Controller\Payload;

use App\Enum\TableColumnType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * New column (name + type) and column rename (name only: the type never changes).
 */
final readonly class TableColumnPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 50)]
        public string $name = '',
        public TableColumnType $type = TableColumnType::TEXT,
    ) {
    }
}
