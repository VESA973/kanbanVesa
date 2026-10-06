<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Repository\TaskRepository;
use App\Service\ProjectProgress;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ProjectProgressTest extends TestCase
{
    public function testOneRowPerMemberPlusUnassignedTasks(): void
    {
        $owner = $this->user(1, 'owner@example.com');
        $alex = $this->user(2, 'alex@example.com');
        $sam = $this->user(3, 'sam@example.com');
        $project = new Project('Projet', $owner);
        $project->addMember($alex, ProjectRole::EDITOR);
        $project->addMember($sam, ProjectRole::VIEWER);
        $repository = $this->createStub(TaskRepository::class);
        $repository->method('countByAssignee')->willReturn([
            2 => ['total' => 4, 'completed' => 3, 'overdue' => 1],
            0 => ['total' => 2, 'completed' => 0, 'overdue' => 0],
        ]);

        $rows = new ProjectProgress($repository, new MockClock())->byMember($project);

        self::assertCount(4, $rows);
        self::assertSame([$owner, $alex, $sam, null], array_map(static fn (\App\Model\MemberProgress $row): ?User => $row->user, $rows));
        self::assertSame([0, 4, 0, 2], array_map(static fn (\App\Model\MemberProgress $row): int => $row->total, $rows));
        self::assertSame(75, $rows[1]->completionRate());
        self::assertSame(1, $rows[1]->open());
        self::assertSame(0, $rows[0]->completionRate(), 'No division by zero without tasks.');
    }

    private function user(int $id, string $email): User
    {
        $user = new User($email, 'Prénom', 'Nom');
        new \ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
