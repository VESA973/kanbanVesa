<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Enum\ProjectRole;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function Zenstruck\Foundry\force;
use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class TrackingTest extends FunctionalTestCase
{
    public function testEditorAssignsAndSchedulesATaskFromTheModal(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $editor = $this->member($project, ProjectRole::EDITOR);
        $task = TaskFactory::new()->inColumn($this->firstColumn($project))->create();
        $client->loginUser($editor);
        $client->request('GET', '/tasks/'.$task->getId());

        $client->submitForm('Enregistrer', [
            'task_form[assignee]' => (string) $editor->getId(),
            'task_form[dueDate]' => '2030-01-15',
            'task_form[priority]' => 'high',
        ]);

        $task = refresh($task);
        self::assertSame($editor->getId(), $task->getAssignee()?->getId());
        self::assertSame('2030-01-15', $task->getDueDate()?->format('Y-m-d'));
        self::assertSame('high', $task->getPriority()->value);
    }

    public function testOnlyMembersCanBeAssigned(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $stranger = UserFactory::createOne();
        $task = TaskFactory::new()->inColumn($this->firstColumn($project))->create();
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/tasks/'.$task->getId());

        $form = $crawler->selectButton('Enregistrer')->form();
        $form->disableValidation()->setValues(['task_form[assignee]' => (string) $stranger->getId()]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertNull(refresh($task)->getAssignee());
    }

    public function testViewerCompletesATaskAssignedToThem(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $viewer = $this->member($project, ProjectRole::VIEWER);
        $mine = TaskFactory::new()->inColumn($this->firstColumn($project))->create(['title' => 'La mienne', 'assignee' => force($viewer)]);
        $other = TaskFactory::new()->inColumn($this->firstColumn($project))->create(['title' => 'Pas la mienne']);
        $client->loginUser($viewer);

        $crawler = $client->request('GET', '/projects/'.$project->getId());
        self::assertCount(1, $crawler->filter('form[action$="/toggle"]'), 'The viewer can only tick their own task.');
        $client->submit($crawler->filter('form[action="/tasks/'.$mine->getId().'/toggle"]')->form());

        self::assertResponseRedirects('/projects/'.$project->getId());
        self::assertTrue(refresh($mine)->isCompleted());

        $client->request('POST', '/tasks/'.$other->getId().'/toggle');
        self::assertFalse(refresh($other)->isCompleted());
    }

    public function testToggleNeverRedirectsToAnotherSite(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        TaskFactory::new()->inColumn($this->firstColumn($project))->create(['assignee' => force($owner)]);
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/my-tasks');

        $form = $crawler->filter('form[action$="/toggle"]')->form();
        $form['_return_to'] = '//evil.example.com';
        $client->submit($form);

        self::assertResponseRedirects('/projects/'.$project->getId());
    }

    public function testMyTasksGroupsMyTasksFromAllProjects(): void
    {
        $client = self::createClient();
        [$me, $project] = $this->project();
        [, $otherProject] = $this->project();
        $otherProject->addMember($me, ProjectRole::EDITOR);
        save($otherProject);
        $column = $this->firstColumn($project);
        TaskFactory::new()->inColumn($column)->create(['title' => 'En retard', 'assignee' => force($me), 'dueDate' => force(new \DateTimeImmutable('-3 days midnight'))]);
        TaskFactory::new()->inColumn($this->firstColumn($otherProject))->create(['title' => 'Plus tard', 'assignee' => force($me), 'dueDate' => force(new \DateTimeImmutable('+10 days midnight'))]);
        TaskFactory::new()->inColumn($column)->create(['title' => 'Pas à moi']);
        $client->loginUser($me);

        $client->request('GET', '/my-tasks');

        self::assertSelectorTextContains('#group-overdue + ul', 'En retard');
        self::assertSelectorTextContains('#group-upcoming + ul', 'Plus tard');
        self::assertSelectorTextNotContains('main', 'Pas à moi');
    }

    public function testOwnerSeesProgressPerMember(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $alex = $this->member($project, ProjectRole::EDITOR);
        $column = $this->firstColumn($project);
        TaskFactory::new()->inColumn($column)->create(['assignee' => force($alex), 'completedAt' => force(new \DateTimeImmutable())]);
        TaskFactory::new()->inColumn($column)->create(['title' => 'Oubliée', 'assignee' => force($alex), 'dueDate' => force(new \DateTimeImmutable('-1 day midnight'))]);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/projects/'.$project->getId().'/progress');

        self::assertResponseIsSuccessful();
        $alexRow = $crawler->filter('tbody tr')->reduce(static fn ($row): bool => str_contains($row->text(), $alex->getFullName()));
        self::assertSame(['2', '1', '1', '1'], $alexRow->filter('td.tabular-nums')->each(static fn ($cell): string => trim($cell->text())));
        self::assertSelectorTextContains('#overdue-heading + ul', 'Oubliée');
    }

    public function testTrackingIsReservedToTheOwner(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $client->loginUser($this->member($project, ProjectRole::EDITOR));

        $client->request('GET', '/projects/'.$project->getId().'/progress');
        self::assertResponseStatusCodeSame(403);

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/projects/'.$project->getId().'/activity');
        self::assertResponseStatusCodeSame(404);
    }

    public function testActivityLogRecordsWhoDidWhat(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/projects/'.$project->getId());
        $client->submit($crawler->filter('form[action$="/tasks"]')->first()->form(['name' => 'Rédiger le cahier des charges']));
        $task = TaskFactory::repository()->findOneBy(['title' => 'Rédiger le cahier des charges']) ?? throw new \LogicException();
        $this->toggle($client, $task->getId() ?? 0);

        $logs = self::getContainer()->get(ActivityLogRepository::class)->findBy(['project' => $project->getId()], ['id' => 'ASC']);
        self::assertSame([ActivityAction::TASK_CREATED, ActivityAction::TASK_COMPLETED], array_map(static fn ($log): ActivityAction => $log->getAction(), $logs));
        self::assertSame($owner->getId(), $logs[0]->getUser()?->getId());

        $client->request('GET', '/projects/'.$project->getId().'/activity');
        self::assertSelectorTextContains('ol li:first-child', 'a terminé « Rédiger le cahier des charges »');
        self::assertSelectorTextContains('ol li:last-child', 'a créé la tâche « Rédiger le cahier des charges » dans « À faire »');
    }

    /**
     * @return array{User, Project}
     */
    private function project(): array
    {
        $owner = UserFactory::createOne();

        return [$owner, ProjectFactory::new()->withColumns('À faire', 'Terminé')->create(['owner' => $owner])];
    }

    private function firstColumn(Project $project): \App\Entity\BoardColumn
    {
        return $project->getColumns()->first() ?: throw new \LogicException('The project has no column.');
    }

    private function member(Project $project, ProjectRole $role): User
    {
        $user = UserFactory::createOne();
        $project->addMember($user, $role);
        save($project);

        return $user;
    }

    private function toggle(KernelBrowser $client, int $taskId): void
    {
        $crawler = $client->request('GET', '/my-tasks');
        $form = $crawler->filter('form[action="/tasks/'.$taskId.'/toggle"]');
        if (0 === $form->count()) {
            $crawler = $client->request('GET', '/tasks/'.$taskId);
            $form = $crawler->filter('form[action="/tasks/'.$taskId.'/toggle"]');
        }
        $client->submit($form->form());
    }
}
