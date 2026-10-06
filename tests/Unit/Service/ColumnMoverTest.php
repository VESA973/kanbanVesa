<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Service\ColumnMover;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ColumnMoverTest extends TestCase
{
    public function testMovesAColumnAndRenumbersTheBoard(): void
    {
        $project = new Project('Projet', new User('owner@example.com', 'Olivia', 'Owner'));
        $todo = $project->addColumn('À faire');
        $doing = $project->addColumn('En cours');
        $done = $project->addColumn('Terminé');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $position = new ColumnMover($entityManager)->move($done, 0);

        self::assertSame(0, $position);
        self::assertSame([1, 2, 0], [$todo->getPosition(), $doing->getPosition(), $done->getPosition()]);
    }
}
