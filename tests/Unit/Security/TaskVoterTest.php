<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Security\Voter\TaskVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class TaskVoterTest extends TestCase
{
    private const int GRANTED = VoterInterface::ACCESS_GRANTED;
    private const int DENIED = VoterInterface::ACCESS_DENIED;

    /**
     * @return iterable<string, array{?ProjectRole, string, int}>
     */
    public static function permissionMatrix(): iterable
    {
        $expected = [
            TaskVoter::VIEW => ['owner' => self::GRANTED, 'editor' => self::GRANTED, 'viewer' => self::GRANTED, 'none' => self::DENIED],
            TaskVoter::EDIT => ['owner' => self::GRANTED, 'editor' => self::GRANTED, 'viewer' => self::DENIED, 'none' => self::DENIED],
            TaskVoter::COMMENT => ['owner' => self::GRANTED, 'editor' => self::GRANTED, 'viewer' => self::GRANTED, 'none' => self::DENIED],
            TaskVoter::COMPLETE => ['owner' => self::GRANTED, 'editor' => self::GRANTED, 'viewer' => self::DENIED, 'none' => self::DENIED],
        ];

        foreach ($expected as $attribute => $results) {
            foreach ($results as $role => $result) {
                yield $attribute.' as '.$role => [ProjectRole::tryFrom($role), $attribute, $result];
            }
        }
    }

    #[DataProvider('permissionMatrix')]
    public function testPermissionMatrix(?ProjectRole $role, string $attribute, int $expected): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $project = new Project('Projet', $owner);
        $task = new Task($project->addColumn('À faire'), 'Tâche', 0, $owner);
        $user = match ($role) {
            ProjectRole::OWNER => $owner,
            null => new User('stranger@example.com', 'Sam', 'Stranger'),
            default => $this->member($project, $role),
        };

        $vote = new TaskVoter()->vote(new UsernamePasswordToken($user, 'main', $user->getRoles()), $task, [$attribute]);

        self::assertSame($expected, $vote);
    }

    public function testViewerCanCompleteOnlyTasksAssignedToThem(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $project = new Project('Projet', $owner);
        $task = new Task($project->addColumn('À faire'), 'Tâche', 0, $owner);
        $viewer = $this->member($project, ProjectRole::VIEWER);
        $token = new UsernamePasswordToken($viewer, 'main', $viewer->getRoles());

        self::assertSame(self::DENIED, new TaskVoter()->vote($token, $task, [TaskVoter::COMPLETE]));

        $task->assign($viewer);
        self::assertSame(self::GRANTED, new TaskVoter()->vote($token, $task, [TaskVoter::COMPLETE]));
        self::assertSame(self::DENIED, new TaskVoter()->vote($token, $task, [TaskVoter::EDIT]), 'Being assigned does not allow editing.');
    }

    public function testTasksOfAnArchivedProjectCanOnlyBeViewed(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $project = new Project('Projet', $owner);
        $task = new Task($project->addColumn('À faire'), 'Tâche', 0, $owner);
        $project->archive();
        $token = new UsernamePasswordToken($owner, 'main', $owner->getRoles());

        self::assertSame(self::GRANTED, new TaskVoter()->vote($token, $task, [TaskVoter::VIEW]));
        foreach ([TaskVoter::EDIT, TaskVoter::COMPLETE, TaskVoter::COMMENT] as $denied) {
            self::assertSame(self::DENIED, new TaskVoter()->vote($token, $task, [$denied]), $denied);
        }
    }

    public function testAnonymousUserIsDenied(): void
    {
        $owner = new User('owner@example.com', 'Olivia', 'Owner');
        $task = new Task(new Project('Projet', $owner)->addColumn('À faire'), 'Tâche', 0, $owner);

        self::assertSame(self::DENIED, new TaskVoter()->vote(new NullToken(), $task, [TaskVoter::VIEW]));
    }

    private function member(Project $project, ProjectRole $role): User
    {
        $user = new User($role->value.'@example.com', 'Membre', ucfirst($role->value));
        $project->addMember($user, $role);

        return $user;
    }
}
