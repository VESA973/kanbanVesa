<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Label;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectColor;
use App\Service\ChecklistManager;
use App\Tests\Unit\BuildsTasks;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ChecklistManagerTest extends TestCase
{
    use BuildsTasks;

    public function testItemsAreAppendedTickedAndRemovedWithoutGaps(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $task = self::newTask(new Project('Projet', $owner)->addColumn('À faire'), 'Tâche', $owner);
        $manager = new ChecklistManager($this->createStub(EntityManagerInterface::class));

        $first = $manager->add($task, ' Maquette ');
        $second = $manager->add($task, 'Intégration');
        $third = $manager->add($task, 'Recette');
        $manager->toggle($second);
        $manager->remove($first);

        self::assertSame('Maquette', $first->getLabel());
        self::assertSame([0, 1], [$second->getPosition(), $third->getPosition()]);
        self::assertSame(1, $task->countDoneChecklistItems());
        self::assertCount(2, $task->getChecklistItems());
    }

    public function testLabelsOfAnotherProjectAreRefused(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $task = self::newTask(new Project('Projet', $owner)->addColumn('À faire'), 'Tâche', $owner);
        $foreignLabel = new Label(new Project('Autre', $owner), 'Bug', ProjectColor::ROSE);

        $this->expectException(\InvalidArgumentException::class);

        $task->replaceLabels([$foreignLabel]);
    }
}
