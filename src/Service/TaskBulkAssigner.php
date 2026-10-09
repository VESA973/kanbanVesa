<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BoardColumn;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds members to every open task of a project (or of one column), keeping the current assignees.
 * One log entry per member instead of one per task, and no e-mail per task: it would flood their inbox.
 */
final readonly class TaskBulkAssigner
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $dispatcher,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<User> $users members of $project
     *
     * @return int the number of tasks that got at least one new assignee
     */
    public function assign(Project $project, array $users, ?BoardColumn $column = null): int
    {
        $tasks = $this->taskRepository->findOpenInProject($project, $column);
        $changed = [];
        foreach ($users as $user) {
            $count = 0;
            foreach ($tasks as $task) {
                if ($task->assign($user)) {
                    ++$count;
                    $changed[spl_object_id($task)] = true;
                }
            }
            $this->log($project, $user, $count, $column);
        }

        $this->entityManager->flush();

        return \count($changed);
    }

    private function log(Project $project, User $user, int $count, ?BoardColumn $column): void
    {
        if (0 === $count) {
            return;
        }

        $this->dispatcher->dispatch(new ProjectActivityEvent($project, ActivityAction::TASKS_BULK_ASSIGNED, $user->getFullName(), [
            'count' => (string) $count,
            'scope' => $column?->getName() ?? $this->translator->trans('bulk_assign.all_columns'),
        ]));
    }
}
