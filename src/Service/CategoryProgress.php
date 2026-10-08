<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Model\BoardProgress;
use App\Repository\CategoryRepository;

final readonly class CategoryProgress
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {
    }

    public function of(Project $project): BoardProgress
    {
        return new BoardProgress($this->categoryRepository->countTasks($project));
    }
}
