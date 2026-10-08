<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Category;

/**
 * Progress of every category of a project, and of the whole project.
 */
final readonly class BoardProgress
{
    /**
     * @param array<int, Progress> $byCategory indexed by category id; missing categories are empty
     */
    public function __construct(
        private array $byCategory,
    ) {
    }

    public function of(Category $category): Progress
    {
        return $this->byCategory[(int) $category->getId()] ?? new Progress();
    }

    /**
     * Weighted by the number of tasks (not an average of the category percentages).
     */
    public function overall(): Progress
    {
        return array_reduce($this->byCategory, static fn (Progress $sum, Progress $progress): Progress => $sum->add($progress), new Progress());
    }
}
