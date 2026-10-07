<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Model\WeeklyCompletions;
use App\Repository\TaskRepository;
use Psr\Clock\ClockInterface;

final readonly class ProjectStatistics
{
    public function __construct(
        private TaskRepository $taskRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Grouping is done in PHP: week functions differ between databases and the volume is small.
     */
    public function weeklyCompletions(Project $project, int $weeks = 8): WeeklyCompletions
    {
        $firstMonday = $this->clock->now()->modify('monday this week')->setTime(0, 0)->modify(\sprintf('-%d weeks', $weeks - 1));
        $mondays = array_map(static fn (int $i): \DateTimeImmutable => $firstMonday->modify(\sprintf('+%d weeks', $i)), range(0, $weeks - 1));

        $counts = [];
        foreach ($this->taskRepository->findCompletionsSince($project, $firstMonday) as $completion) {
            $week = intdiv((int) $firstMonday->diff($completion['completedAt'])->days, 7);
            $assigneeId = $completion['assigneeId'] ?? 0;
            $counts[$assigneeId][$week] = ($counts[$assigneeId][$week] ?? 0) + 1;
        }

        $rows = [];
        foreach ($project->getMembers() as $member) {
            $rows[] = $this->row($member->getUser(), $counts[$member->getUser()->getId()] ?? [], $weeks);
        }
        if (isset($counts[0])) {
            $rows[] = $this->row(null, $counts[0], $weeks);
        }

        return new WeeklyCompletions($mondays, $rows);
    }

    /**
     * @param array<int, int> $countsByWeek
     *
     * @return array{user: ?User, counts: list<int>, total: int}
     */
    private function row(?User $user, array $countsByWeek, int $weeks): array
    {
        $counts = array_map(static fn (int $week): int => $countsByWeek[$week] ?? 0, range(0, $weeks - 1));

        return ['user' => $user, 'counts' => $counts, 'total' => array_sum($counts)];
    }
}
