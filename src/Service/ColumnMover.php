<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BoardColumn;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ColumnMover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int the confirmed position of the column
     */
    public function move(BoardColumn $column, int $position): int
    {
        $position = PositionList::insert($column->getProject()->getColumns(), $column, $position);
        $this->entityManager->flush();

        return $position;
    }
}
