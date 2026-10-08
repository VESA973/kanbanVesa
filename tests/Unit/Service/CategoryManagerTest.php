<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Exception\CategoryException;
use App\Repository\CategoryRepository;
use App\Service\CategoryManager;
use App\Tests\Unit\BuildsTasks;
use PHPUnit\Framework\TestCase;

final class CategoryManagerTest extends TestCase
{
    use BuildsTasks;

    private Project $project;
    private RecordingDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->project = new Project('Projet', new User('owner@example.com', 'Olivia', 'Owner'));
        $this->dispatcher = new RecordingDispatcher();
    }

    public function testCreatesCategoriesAtTheEnd(): void
    {
        $this->project->addCategory('Général');

        $category = $this->manager()->create($this->project, 'Design');

        self::assertSame(1, $category->getPosition());
        self::assertSame([ActivityAction::CATEGORY_CREATED], $this->dispatcher->actions());
    }

    public function testRenamesAndLogsThePreviousName(): void
    {
        $category = $this->project->addCategory('Général');

        $this->manager()->rename($category, 'Communication');

        self::assertSame('Communication', $category->getName());
        self::assertSame(['previous' => 'Général'], $this->dispatcher->last()->payload);
    }

    public function testDeletingMovesTasksToTheBottomOfTheSameColumnsOfTheTarget(): void
    {
        $todo = $this->project->addColumn('À faire');
        $done = $this->project->addColumn('Terminé');
        $general = $this->project->addCategory('Général');
        $design = $this->project->addCategory('Design');
        $existing = self::newTask($todo, 'Existante', null, $general);
        $moved = self::newTask($todo, 'Maquette', null, $design);
        $finished = self::newTask($done, 'Logo', null, $design);

        $this->manager()->delete($design, $general);

        self::assertSame([$general, $general], [$moved->getCategory(), $finished->getCategory()]);
        self::assertSame([$todo, $done], [$moved->getColumn(), $finished->getColumn()], 'Tasks keep their column.');
        self::assertSame([0, 1, 0], [$existing->getPosition(), $moved->getPosition(), $finished->getPosition()]);
        self::assertCount(3, $general->getTasks());
        self::assertSame(['count' => '2', 'target' => 'Général'], $this->dispatcher->last()->payload);
    }

    public function testAnEmptyCategoryNeedsNoTarget(): void
    {
        $this->project->addCategory('Général');
        $empty = $this->project->addCategory('Vide');

        $this->manager()->delete($empty);

        self::assertSame([ActivityAction::CATEGORY_DELETED], $this->dispatcher->actions());
    }

    public function testRefusesToDeleteTheLastCategory(): void
    {
        $only = $this->project->addCategory('Général');

        $this->expectExceptionObject(CategoryException::lastCategory());

        $this->manager()->delete($only);
    }

    public function testRefusesToDeleteACategoryWithTasksWithoutAValidTarget(): void
    {
        $column = $this->project->addColumn('À faire');
        $this->project->addCategory('Général');
        $design = $this->project->addCategory('Design');
        self::newTask($column, 'Maquette', null, $design);
        $foreign = new Project('Autre', $this->project->getOwner())->addCategory('Général');

        foreach ([null, $design, $foreign] as $target) {
            try {
                $this->manager()->delete($design, $target);
                self::fail('The deletion should have been refused.');
            } catch (CategoryException $exception) {
                self::assertSame('category.error.invalid_target', $exception->getMessage());
            }
        }
        self::assertCount(1, $design->getTasks(), 'Nothing was moved.');
    }

    private function manager(): CategoryManager
    {
        return new CategoryManager($this->createStub(CategoryRepository::class), $this->dispatcher);
    }
}
