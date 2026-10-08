<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Project;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use App\Exception\CategoryException;
use App\Repository\CategoryRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class CategoryManager
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function create(Project $project, string $name): Category
    {
        $category = $project->addCategory($name);
        $this->dispatcher->dispatch(new ProjectActivityEvent($project, ActivityAction::CATEGORY_CREATED, $name));
        $this->categoryRepository->save($category);

        return $category;
    }

    public function rename(Category $category, string $name): void
    {
        $this->dispatcher->dispatch(new ProjectActivityEvent($category->getProject(), ActivityAction::CATEGORY_RENAMED, $name, [
            'previous' => $category->getName(),
        ]));
        $category->rename($name);
        $this->categoryRepository->save($category);
    }

    /**
     * Deletes the category; its tasks are first moved to the bottom of the same columns in $target.
     *
     * @throws CategoryException when it is the last category, or when it has tasks and $target is not a sibling
     */
    public function delete(Category $category, ?Category $target = null): void
    {
        $project = $category->getProject();
        if ($project->getCategories()->count() <= 1) {
            throw CategoryException::lastCategory();
        }

        $tasks = $category->getTasks()->getValues();
        if ([] !== $tasks && (null === $target || $target === $category || $target->getProject() !== $project)) {
            throw CategoryException::invalidTarget();
        }

        foreach ($tasks as $task) {
            $column = $task->getColumn();
            $task->moveTo($column, $target, \count($column->getTasksIn($target)));
            $target->getTasks()->add($task);
        }
        $category->getTasks()->clear();

        $this->dispatcher->dispatch(new ProjectActivityEvent($project, ActivityAction::CATEGORY_DELETED, $category->getName(), [
            'count' => (string) \count($tasks),
            'target' => $target?->getName() ?? '',
        ]));
        $this->categoryRepository->remove($category);
    }
}
