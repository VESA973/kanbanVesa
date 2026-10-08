<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Entity\Category;
use App\Entity\Project;
use App\Entity\User;
use App\Model\BoardProgress;
use App\Model\Progress;
use PHPUnit\Framework\TestCase;

final class BoardProgressTest extends TestCase
{
    public function testPercentOfACategory(): void
    {
        self::assertSame(33, new Progress(3, 1)->percent(), 'Rounded down: 100 % only when everything is done.');
        self::assertSame(100, new Progress(2, 2)->percent());
    }

    public function testAnEmptyCategoryIsNotZeroPercentButEmpty(): void
    {
        $empty = new Progress();

        self::assertTrue($empty->isEmpty());
        self::assertSame(0, $empty->percent(), 'No division by zero.');
    }

    public function testOverallProgressIsWeightedByTheNumberOfTasks(): void
    {
        $project = new Project('Projet', new User('owner@example.com', 'Olivia', 'Owner'));
        $big = $this->category($project, 1);
        $small = $this->category($project, 2);
        $empty = $this->category($project, 3);

        $progress = new BoardProgress([1 => new Progress(9, 9), 2 => new Progress(1, 0)]);

        self::assertSame(100, $progress->of($big)->percent());
        self::assertSame(0, $progress->of($small)->percent());
        self::assertTrue($progress->of($empty)->isEmpty());
        // 9 done out of 10 tasks, not the average of 100 % and 0 %.
        self::assertSame(90, $progress->overall()->percent());
    }

    private function category(Project $project, int $id): Category
    {
        $category = $project->addCategory('Catégorie '.$id);
        new \ReflectionProperty(Category::class, 'id')->setValue($category, $id);

        return $category;
    }
}
