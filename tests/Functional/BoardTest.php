<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class BoardTest extends FunctionalTestCase
{
    public function testNewProjectStartsWithThreeColumns(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/projects/new');
        $client->submitForm('Créer le projet', ['project_form[name]' => 'Kanban']);
        $client->followRedirect();

        self::assertSelectorCount(3, 'main h2[id^="column-"]');
        self::assertAnySelectorTextContains('main h2[id^="column-"]', 'À faire');
        self::assertAnySelectorTextContains('main h2[id^="column-"]', 'Terminé');
    }

    public function testBoardShowsColumnsAndTasksInOrder(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $column = $project->getColumns()->first() ?: throw new \LogicException();
        TaskFactory::new()->inColumn($column)->create(['title' => 'Première']);
        TaskFactory::new()->inColumn($column)->create(['title' => 'Deuxième']);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/projects/'.$project->getId());

        self::assertSame(['Première', 'Deuxième'], $crawler->filter('ul[data-column-id="'.$column->getId().'"] li a')->each(static fn ($node): string => trim($node->text())));
    }

    public function testEditorAddsATaskInline(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $editor = $this->member($project, ProjectRole::EDITOR);
        $client->loginUser($editor);
        $crawler = $client->request('GET', '/projects/'.$project->getId());

        $form = $crawler->filter('form[action$="/tasks"]')->first()->form(['name' => 'Écrire les specs ✍️']);
        $client->submit($form);

        self::assertResponseRedirects('/projects/'.$project->getId());
        $task = TaskFactory::repository()->findOneBy(['title' => 'Écrire les specs ✍️']);
        self::assertNotNull($task);
        self::assertSame($editor->getId(), $task->getCreatedBy()?->getId());
    }

    public function testViewerSeesTheBoardWithoutEditingControls(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $client->loginUser($this->member($project, ProjectRole::VIEWER));

        $client->request('GET', '/projects/'.$project->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-controller~="sortable"]');
        self::assertSelectorNotExists('form[action$="/tasks"]');
        self::assertSelectorNotExists('form[action$="/columns"]');
    }

    public function testDragAndDropMovesATaskToAnotherColumn(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        [$todo, $doing] = $project->getColumns()->toArray();
        $task = TaskFactory::new()->inColumn($todo)->create();
        TaskFactory::new()->inColumn($doing)->create();
        $client->loginUser($owner);

        $this->patch($client, '/tasks/'.$task->getId().'/move', ['columnId' => $doing->getId(), 'position' => 0], $this->boardToken($client, $project));

        self::assertResponseIsSuccessful();
        self::assertSame(['columnId' => $doing->getId(), 'categoryId' => $task->getCategory()->getId(), 'position' => 0], json_decode((string) $client->getResponse()->getContent(), true));
        self::assertSame($doing->getId(), refresh($task)->getColumn()->getId());
    }

    public function testMoveWithoutValidCsrfTokenIsRejected(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $task = TaskFactory::new()->inColumn($project->getColumns()->first() ?: throw new \LogicException())->create();
        $client->loginUser($owner);

        $this->patch($client, '/tasks/'.$task->getId().'/move', ['position' => 0], 'forged-token');

        self::assertFalse($client->getResponse()->isSuccessful());
    }

    public function testCannotMoveATaskIntoAnotherProject(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $task = TaskFactory::new()->inColumn($project->getColumns()->first() ?: throw new \LogicException())->create();
        $foreignColumn = ProjectFactory::new()->withColumns('Ailleurs')->create(['owner' => $owner])->getColumns()->first() ?: throw new \LogicException();
        $client->loginUser($owner);

        $this->patch($client, '/tasks/'.$task->getId().'/move', ['columnId' => $foreignColumn->getId(), 'position' => 0], $this->boardToken($client, $project));

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * The viewer's board exposes no CSRF token at all (no drag & drop), and the
     * voter would refuse anyway (see TaskVoterTest): the task never moves.
     */
    public function testViewerCannotMoveATask(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        [$todo, $doing] = $project->getColumns()->toArray();
        $task = TaskFactory::new()->inColumn($todo)->create();
        $client->loginUser($this->member($project, ProjectRole::VIEWER));

        $client->request('GET', '/projects/'.$project->getId());
        self::assertSelectorNotExists('[data-sortable-csrf-token-value]');

        $this->patch($client, '/tasks/'.$task->getId().'/move', ['columnId' => $doing->getId(), 'position' => 0], 'guessed-token');
        self::assertFalse($client->getResponse()->isSuccessful());
        self::assertSame($todo->getId(), refresh($task)->getColumn()->getId());
    }

    public function testDragAndDropReordersColumns(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $done = $project->getColumns()->last() ?: throw new \LogicException();
        $client->loginUser($owner);

        $this->patch($client, '/columns/'.$done->getId().'/move', ['position' => 0], $this->boardToken($client, $project));

        self::assertResponseIsSuccessful();
        self::assertSame(0, refresh($done)->getPosition());
    }

    public function testTaskDetailOpensInTheModalFrame(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $task = TaskFactory::new()->inColumn($project->getColumns()->first() ?: throw new \LogicException())->create(['title' => 'Détail']);
        $client->loginUser($owner);

        $client->request('GET', '/tasks/'.$task->getId(), server: ['HTTP_TURBO_FRAME' => 'modal']);

        self::assertSelectorExists('turbo-frame#modal dialog[aria-labelledby="modal-title"]');
        self::assertSelectorTextContains('#modal-title', 'Détail');
        self::assertSelectorNotExists('header nav');
    }

    public function testEditorEditsAndMovesATaskFromTheModal(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        [$todo, , $done] = $project->getColumns()->toArray();
        $task = TaskFactory::new()->inColumn($todo)->create();
        $client->loginUser($this->member($project, ProjectRole::EDITOR));
        $client->request('GET', '/tasks/'.$task->getId());

        $client->submitForm('Enregistrer', [
            'task_form[title]' => 'Titre modifié',
            'task_form[description]' => 'Une description',
            'task_form[column]' => (string) $done->getId(),
        ]);

        self::assertResponseRedirects('/projects/'.$project->getId());
        $task = refresh($task);
        self::assertSame('Titre modifié', $task->getTitle());
        self::assertSame($done->getId(), $task->getColumn()->getId());
    }

    public function testViewerSeesTheTaskReadOnly(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $task = TaskFactory::new()->inColumn($project->getColumns()->first() ?: throw new \LogicException())->create();
        $client->loginUser($this->member($project, ProjectRole::VIEWER));

        $client->request('GET', '/tasks/'.$task->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="task_form"]');

        $client->request('POST', '/tasks/'.$task->getId(), ['task_form' => ['title' => 'Piraté']]);
        self::assertNotSame('Piraté', refresh($task)->getTitle());
    }

    public function testNonMemberGetsA404OnTasksAndColumns(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $column = $project->getColumns()->first() ?: throw new \LogicException();
        $task = TaskFactory::new()->inColumn($column)->create();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/tasks/'.$task->getId());
        self::assertResponseStatusCodeSame(404);

        // The CSRF check runs before the voter: a forged request is rejected before reaching it.
        $client->request('POST', '/columns/'.$column->getId().'/tasks', ['name' => 'Intrus']);
        self::assertFalse($client->getResponse()->isSuccessful());
        self::assertSame(1, TaskFactory::repository()->count());
    }

    public function testOwnerManagesColumns(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $todo = $project->getColumns()->first() ?: throw new \LogicException();
        TaskFactory::new()->inColumn($todo)->create();
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/projects/'.$project->getId());
        $client->submit($crawler->filter('form[action$="/columns"]')->form(['name' => 'Recette']));
        self::assertResponseRedirects();

        $crawler = $client->request('GET', '/projects/'.$project->getId());
        $client->submit($crawler->filter('form[action$="/columns/'.$todo->getId().'/rename"]')->form(['name' => 'Backlog']));
        $crawler = $client->followRedirect();
        self::assertAnySelectorTextContains('main h2[id^="column-"]', 'Backlog');
        self::assertAnySelectorTextContains('main h2[id^="column-"]', 'Recette');

        $client->submit($crawler->filter('form[action$="/columns/'.$todo->getId().'/delete"]')->form());
        $client->followRedirect();
        self::assertSelectorCount(3, 'main h2[id^="column-"]');
        self::assertSame(0, TaskFactory::repository()->count(), 'Tasks of a deleted column are deleted too.');
    }

    public function testEditorDeletesATask(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $task = TaskFactory::new()->inColumn($project->getColumns()->first() ?: throw new \LogicException())->create();
        $client->loginUser($this->member($project, ProjectRole::EDITOR));
        $client->request('GET', '/tasks/'.$task->getId());

        $client->submitForm('Supprimer la tâche');

        self::assertResponseRedirects('/projects/'.$project->getId());
        self::assertSame(0, TaskFactory::repository()->count());
    }

    /**
     * @return array{User, Project}
     */
    private function project(): array
    {
        $owner = UserFactory::createOne();

        return [$owner, ProjectFactory::new()->withColumns('À faire', 'En cours', 'Terminé')->create(['owner' => $owner])];
    }

    private function member(Project $project, ProjectRole $role): User
    {
        $user = UserFactory::createOne();
        $project->addMember($user, $role);
        save($project);

        return $user;
    }

    private function boardToken(KernelBrowser $client, Project $project): string
    {
        $crawler = $client->request('GET', '/projects/'.$project->getId());
        $token = $crawler->filter('[data-sortable-csrf-token-value]')->first()->attr('data-sortable-csrf-token-value');
        self::assertNotNull($token, 'The board exposes a CSRF token to the drag & drop.');

        return $token;
    }

    /**
     * @param array<string, int|null> $payload
     */
    private function patch(KernelBrowser $client, string $url, array $payload, string $csrfToken): void
    {
        $client->request('PATCH', $url, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrfToken,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
