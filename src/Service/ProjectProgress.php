<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Model\MemberProgress;
use App\Repository\TaskRepository;
use Psr\Clock\ClockInterface;

final readonly class ProjectProgress
{
    public function __construct(
        private TaskRepository $taskRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * One row per member (even without tasks), then unassigned tasks if any.
     *
     * @return list<MemberProgress>
     */
    public function byMember(Project $project): array
    {
        $counters = $this->taskRepository->countByAssignee($project, $this->clock->now());

        $rows = [];
        foreach ($project->getMembers() as $member) {
            $count = $counters[$member->getUser()->getId()] ?? ['total' => 0, 'completed' => 0, 'overdue' => 0];
            $rows[] = new MemberProgress($member->getUser(), $count['total'], $count['completed'], $count['overdue']);
        }

        $unassigned = $counters[0] ?? null;
        if (null !== $unassigned) {
            $rows[] = new MemberProgress(null, $unassigned['total'], $unassigned['completed'], $unassigned['overdue']);
        }

        return $rows;
    }
}
