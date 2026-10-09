<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\BoardColumn;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ActivityAction;
use App\Enum\ProjectRole;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function Zenstruck\Foundry\force;
use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class CollaborationTest extends FunctionalTestCase
{
    public function testViewerCommentsFromTheModal(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $viewer = $this->member($project, ProjectRole::VIEWER);
        $task = TaskFactory::new()->inColumn($this->column($project))->create();
        $client->loginUser($viewer);

        $client->request('GET', '/tasks/'.$task->getId(), server: ['HTTP_TURBO_FRAME' => 'modal']);
        $client->submitForm('Commenter', ['content' => 'Je m’en occupe demain 👍']);

        self::assertResponseRedirects('/tasks/'.$task->getId(), message: 'The modal re-renders with the new comment.');
        $client->followRedirect();
        self::assertSelectorTextContains('#comments-title + ol', 'Je m’en occupe demain 👍');
        $logs = self::getContainer()->get(ActivityLogRepository::class)->findBy(['action' => ActivityAction::COMMENT_ADDED]);
        self::assertCount(1, $logs);
    }

    public function testOnlyTheAuthorOrTheOwnerDeletesAComment(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $author = $this->member($project, ProjectRole::VIEWER);
        $editor = $this->member($project, ProjectRole::EDITOR);
        $task = TaskFactory::new()->inColumn($this->column($project))->create();
        $client->loginUser($author);
        $client->request('GET', '/tasks/'.$task->getId());
        $client->submitForm('Commenter', ['content' => 'Mon avis']);

        $client->loginUser($editor);
        $client->request('GET', '/tasks/'.$task->getId());
        self::assertSelectorNotExists('form[action$="/delete"][data-confirm-message-value*="commentaire"]');

        $client->loginUser($owner);
        $client->request('GET', '/tasks/'.$task->getId());
        $client->submit($client->getCrawler()->filter('form[action^="/comments/"]')->form());

        self::assertCount(0, refresh($task)->getComments());
    }

    public function testChecklistIsBuiltByEditorsAndTickedByTheAssignedViewer(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $editor = $this->member($project, ProjectRole::EDITOR);
        $viewer = $this->member($project, ProjectRole::VIEWER);
        $task = TaskFactory::new()->inColumn($this->column($project))->assignedTo($viewer)->create();
        $client->loginUser($editor);
        $client->request('GET', '/tasks/'.$task->getId());
        $client->submitForm('Ajouter', ['name' => 'Maquette']);
        $client->followRedirect();
        $client->submitForm('Ajouter', ['name' => 'Recette']);

        $client->loginUser($viewer);
        $crawler = $client->request('GET', '/tasks/'.$task->getId());
        self::assertSelectorNotExists('form[action$="/checklist"]', 'The viewer cannot add items.');
        $client->submit($crawler->filter('form[action$="/toggle"][action^="/checklist-items/"]')->first()->form());

        self::assertSame(1, refresh($task)->countDoneChecklistItems());
        $crawler = $client->request('GET', '/projects/'.$project->getId());
        self::assertStringContainsString('1/2', $crawler->filter('main li')->text());
    }

    public function testLabelsAreManagedPutOnTasksAndUsedAsFilter(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $editor = $this->member($project, ProjectRole::EDITOR);
        $tagged = TaskFactory::new()->inColumn($this->column($project))->create(['title' => 'Bug du menu']);
        TaskFactory::new()->inColumn($this->column($project))->create(['title' => 'Autre tâche']);
        $client->loginUser($editor);

        $client->request('GET', '/projects/'.$project->getId().'/labels');
        $client->submitForm("Créer l'étiquette", ['label_form[name]' => 'Bug', 'label_form[color]' => 'rose']);
        self::assertResponseRedirects();
        $client->request('GET', '/projects/'.$project->getId().'/labels');
        $client->submitForm("Créer l'étiquette", ['label_form[name]' => 'Bug']);
        self::assertResponseStatusCodeSame(422, 'Label names are unique per project.');

        $label = refresh($project)->getLabels()->first() ?: throw new \LogicException();
        $crawler = $client->request('GET', '/tasks/'.$tagged->getId());
        $client->submit($crawler->selectButton('Enregistrer')->form(), ['task_form[labels][0]' => (string) $label->getId()]);

        $crawler = $client->request('GET', '/projects/'.$project->getId().'?label='.$label->getId());
        self::assertSame(['Bug du menu'], $crawler->filter('main li a[data-turbo-frame="modal"]')->each(static fn ($a): string => trim($a->text())));
        self::assertSelectorNotExists('[data-controller~="sortable"]', 'Drag & drop is disabled while filtering.');
        self::assertSelectorTextContains('main li', 'Bug');
    }

    public function testViewerCannotManageLabels(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $client->loginUser($this->member($project, ProjectRole::VIEWER));

        $client->request('GET', '/projects/'.$project->getId().'/labels');

        self::assertResponseStatusCodeSame(403);
    }

    public function testFiltersByAssigneeAndDueDate(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $column = $this->column($project);
        TaskFactory::new()->inColumn($column)->assignedTo($owner)->create(['title' => 'À moi en retard', 'dueDate' => force(new \DateTimeImmutable('-2 days midnight'))]);
        TaskFactory::new()->inColumn($column)->create(['title' => 'À personne']);
        $client->loginUser($owner);

        $titles = fn (string $query): array => $client->request('GET', '/projects/'.$project->getId().$query)
            ->filter('main li a[data-turbo-frame="modal"]')->each(static fn ($a): string => trim($a->text()));

        self::assertSame(['À moi en retard'], $titles('?assignee='.$owner->getId()));
        self::assertSame(['À personne'], $titles('?assignee=none&label=&due='));
        self::assertSame(['À moi en retard'], $titles('?due=overdue'));
        self::assertSame(['À moi en retard', 'À personne'], $titles('?assignee=&label=&due='));
    }

    public function testAssigningSomeoneElseQueuesAnEmail(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $alex = $this->member($project, ProjectRole::EDITOR);
        $task = TaskFactory::new()->inColumn($this->column($project))->create();
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/tasks/'.$task->getId());

        $index = array_search((string) $alex->getId(), $crawler->filter('input[name="task_form[assignees][]"]')->extract(['value']), true);
        $client->submitForm('Enregistrer', [\sprintf('task_form[assignees][%d]', $index) => (string) $alex->getId()]);

        self::assertQueuedEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage() ?? throw new \LogicException(), 'To', $alex->getEmail());
    }

    public function testDueReminderCommandQueuesOneEmailPerTask(): void
    {
        self::bootKernel();
        [$owner, $project] = $this->project();
        TaskFactory::new()->inColumn($this->column($project))->assignedTo($owner)->create(['dueDate' => force(new \DateTimeImmutable('tomorrow'))]);
        TaskFactory::new()->inColumn($this->column($project))->assignedTo($owner)->create(['dueDate' => force(new \DateTimeImmutable('+5 days midnight'))]);
        $command = new CommandTester(new Application(self::$kernel ?? throw new \LogicException())->find('app:tasks:remind-due'));

        $command->execute([]);
        self::assertStringContainsString('1 reminder(s) queued.', $command->getDisplay());

        $command->execute([]);
        self::assertStringContainsString('0 reminder(s) queued.', $command->getDisplay(), 'No duplicate reminder.');
    }

    /**
     * @return array{User, Project}
     */
    private function project(): array
    {
        $owner = UserFactory::createOne();

        return [$owner, ProjectFactory::new()->withColumns('À faire')->create(['owner' => $owner])];
    }

    private function column(Project $project): BoardColumn
    {
        return $project->getColumns()->first() ?: throw new \LogicException();
    }

    private function member(Project $project, ProjectRole $role): User
    {
        $user = UserFactory::createOne();
        $project->addMember($user, $role);
        save($project);

        return $user;
    }
}
