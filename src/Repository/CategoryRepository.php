<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Project;
use App\Entity\Task;
use App\Model\Progress;
use App\Service\PositionList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findInProject(Project $project, ?int $id): ?Category
    {
        return null === $id ? null : $this->findOneBy(['id' => $id, 'project' => $project]);
    }

    public function save(Category $category): void
    {
        $this->getEntityManager()->persist($category);
        $this->getEntityManager()->flush();
    }

    public function remove(Category $category): void
    {
        $project = $category->getProject();
        $project->getCategories()->removeElement($category);
        PositionList::remove($project->getCategories(), $category);
        $this->getEntityManager()->remove($category);
        $this->getEntityManager()->flush();
    }

    /**
     * Task counters per category id, in a single query. Empty categories are absent.
     *
     * @return array<int, Progress>
     */
    public function countTasks(Project $project): array
    {
        /** @var list<array{categoryId: int|string, total: int|string, completed: int|string|null}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(t.category) AS categoryId')
            ->addSelect('COUNT(t.id) AS total')
            ->addSelect('SUM(CASE WHEN t.completedAt IS NOT NULL THEN 1 ELSE 0 END) AS completed')
            ->from(Task::class, 't')
            ->innerJoin('t.category', 'c')
            ->andWhere('c.project = :project')
            ->setParameter('project', $project)
            ->groupBy('t.category')
            ->getQuery()
            ->getArrayResult();

        $counters = [];
        foreach ($rows as $row) {
            $counters[(int) $row['categoryId']] = new Progress((int) $row['total'], (int) $row['completed']);
        }

        return $counters;
    }
}
