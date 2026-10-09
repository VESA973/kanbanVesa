<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Entity\Task;

/**
 * Groups the tasks of the "Mes tâches" page: by urgency, and by chantier for the ones under the user's responsibility.
 */
final class TaskAgenda
{
    public const array GROUPS = ['overdue', 'today', 'upcoming', 'no_due_date', 'completed'];

    /**
     * Tasks completed more than this long ago are no longer shown.
     */
    private const string COMPLETED_VISIBILITY = '-7 days';

    /**
     * @param iterable<Task> $tasks
     *
     * @return array<value-of<self::GROUPS>, list<Task>> every group, possibly empty, in display order
     */
    public static function group(iterable $tasks, \DateTimeImmutable $now): array
    {
        $groups = array_fill_keys(self::GROUPS, []);
        foreach ($tasks as $task) {
            $group = self::groupOf($task, $now);
            if (null !== $group) {
                $groups[$group][] = $task;
            }
        }

        return $groups;
    }

    /**
     * Keeps the order of the tasks, and of the projects as they first appear.
     *
     * @param iterable<Task> $tasks
     *
     * @return list<array{project: Project, tasks: list<Task>}>
     */
    public static function byProject(iterable $tasks): array
    {
        $groups = [];
        foreach ($tasks as $task) {
            $project = $task->getProject();
            $groups[spl_object_id($project)]['project'] = $project;
            $groups[spl_object_id($project)]['tasks'][] = $task;
        }

        return array_values($groups);
    }

    /**
     * @return value-of<self::GROUPS>|null
     */
    private static function groupOf(Task $task, \DateTimeImmutable $now): ?string
    {
        $today = $now->setTime(0, 0);
        $dueDate = $task->getDueDate();

        return match (true) {
            $task->isCompleted() => $task->getCompletedAt() >= $now->modify(self::COMPLETED_VISIBILITY) ? 'completed' : null,
            null === $dueDate => 'no_due_date',
            $dueDate < $today => 'overdue',
            $dueDate->format('Y-m-d') === $today->format('Y-m-d') => 'today',
            default => 'upcoming',
        };
    }
}
