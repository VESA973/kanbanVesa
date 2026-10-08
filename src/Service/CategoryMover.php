<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CategoryMover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int the confirmed position of the category
     */
    public function move(Category $category, int $position): int
    {
        $position = PositionList::insert($category->getProject()->getCategories(), $category, $position);
        $this->entityManager->flush();

        return $position;
    }
}
