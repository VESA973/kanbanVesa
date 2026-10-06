<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * An element ordered by an integer position inside a list (columns of a board, tasks of a column).
 */
interface Positionable
{
    public function setPosition(int $position): void;
}
