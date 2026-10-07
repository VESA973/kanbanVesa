<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Repository\TaskRepository;
use App\Service\ProjectStatistics;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ProjectStatisticsTest extends TestCase
{
    public function testCountsCompletionsPerMemberAndWeek(): void
    {
        $owner = $this->user(1, 'owner@example.com');
        $alex = $this->user(2, 'alex@example.com');
        $project = new Project('Projet', $owner);
        $project->addMember($alex, ProjectRole::EDITOR);
        $repository = $this->createMock(TaskRepository::class);
        $repository->expects($this->once())->method('findCompletionsSince')
            ->with($project, $this->callback(static fn (\DateTimeImmutable $since): bool => '2026-08-17' === $since->format('Y-m-d')))
            ->willReturn([
                ['assigneeId' => 2, 'completedAt' => new \DateTimeImmutable('2026-10-07 09:00')],
                ['assigneeId' => 2, 'completedAt' => new \DateTimeImmutable('2026-10-05 18:00')],
                ['assigneeId' => 2, 'completedAt' => new \DateTimeImmutable('2026-08-17 08:00')],
                ['assigneeId' => null, 'completedAt' => new \DateTimeImmutable('2026-09-30 12:00')],
            ]);

        $stats = new ProjectStatistics($repository, new MockClock('2026-10-07 15:00'))->weeklyCompletions($project);

        self::assertCount(8, $stats->weeks);
        self::assertSame('2026-08-17', $stats->weeks[0]->format('Y-m-d'));
        self::assertSame('2026-10-05', $stats->weeks[7]->format('Y-m-d'));
        self::assertSame([0, 0, 0, 0, 0, 0, 0, 0], $stats->rows[0]['counts'], 'The owner completed nothing.');
        self::assertSame([1, 0, 0, 0, 0, 0, 0, 2], $stats->rows[1]['counts']);
        self::assertNull($stats->rows[2]['user']);
        self::assertSame(1, $stats->rows[2]['counts'][6]);
        self::assertSame(2, $stats->max());
        self::assertFalse($stats->isEmpty());
    }

    private function user(int $id, string $email): User
    {
        $user = new User($email, 'Prénom', 'Nom');
        new \ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
