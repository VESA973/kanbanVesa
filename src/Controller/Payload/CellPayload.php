<?php

declare(strict_types=1);

namespace App\Controller\Payload;

/**
 * JSON body sent by table_cell_controller.js; the value is validated by TableCellNormalizer.
 */
final readonly class CellPayload
{
    public function __construct(
        public ?string $value = null,
    ) {
    }
}
