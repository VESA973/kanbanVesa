<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function Zenstruck\Foundry\force;
use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class CategoryTest extends FunctionalTestCase
{
    public function testNewProjectStartsWithTheGeneralCategory(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/projects/new');
        $client->submitForm('Créer le projet', ['project_form[name]' => 'Kanban']);
        $client->followRedirect();

        self::assertSelectorCount(1, 'main h2[id^="category-"]');
        self::assertSelectorTextContains('main h2[id^="category-"]', 'Général');
        self::assertSelectorTextContains('main', '0 tâche');
    }

    public function testEditorCreatesRenamesAndAddsTasksToACategory(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $client->loginUser($this->member($project, ProjectRole::EDITOR));

        $crawler = $client->request('GET', '/projects/'.$project->getId());
        $client->submit($crawler->filter('form[action$="/categories"]')->form(['name' => 'Design']));
        $crawler = $client->followRedirect();
        $design = self::getContainer()->get(CategoryRepository::class)->findOneBy(['name' => 'Design']) ?? throw new \LogicException();
        self::assertSame($project->getId(), $design->getProject()->getId());

        $client->submit($crawler->filter('form[action$="/categories/'.$design->getId().'/rename"]')->form(['name' => 'Maquettes']));
        $crawler = $client->followRedirect();
        self::assertAnySelectorTextContains('main h2[id^="category-"]', 'Maquettes');

        $designForms = $crawler->filter('form[action$="/tasks"]')->reduce(static fn ($form): bool => $form->filter('input[name="categoryId"]')->attr('value') === (string) $design->getId());
        $client->submit($designForms->first()->form(['name' => 'Logo']));
        $client->followRedirect();
        $task = TaskFactory::repository()->findOneBy(['title' => 'Logo']) ?? throw new \LogicException();
        self::assertSame($design->getId(), $task->getCategory()->getId());
        self::assertSelectorTextContains('ul[data-category-id="'.$design->getId().'"]', 'Logo');
    }

    public function testShowsTheProgressOfEachCategoryAndOfTheProject(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        [$todo, , $done] = $project->getColumns()->toArray();
        $general = $project->getCategories()->first() ?: throw new \LogicException();
        $design = $project->addCategory('Design');
        $empty = $project->addCategory('Vide');
        save($project);
        TaskFactory::new()->inColumn($done, $general)->create(['completedAt' => force(new \DateTimeImmutable())]);
        TaskFactory::new()->inColumn($todo, $general)->create();
        TaskFactory::new()->inColumn($todo, $design)->create();
        TaskFactory::new()->inColumn($todo, $design)->create();
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/projects/'.$project->getId());

        // 1 done out of 4 tasks: 25 %, not the average of 50 % and 0 %.
        self::assertSame('25', $crawler->filter('#overall-progress-title + div [role="progressbar"]')->attr('aria-valuenow'));
        self::assertSame(['50', '0'], $crawler->filter('section[aria-labelledby^="category-"] [role="progressbar"]')->each(static fn ($bar): string => (string) $bar->attr('aria-valuenow')));
        self::assertSelectorTextContains('section[aria-labelledby="category-'.$empty->getId().'-title"] header', '0 tâche');
    }

    public function testDeletingACategoryMovesItsTasks(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        [$todo] = $project->getColumns()->toArray();
        $general = $project->getCategories()->first() ?: throw new \LogicException();
        $design = $project->addCategory('Design');
        save($project);
        $task = TaskFactory::new()->inColumn($todo, $design)->create(['title' => 'Maquette']);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/projects/'.$project->getId());
        $form = $crawler->filter('form[action$="/categories/'.$design->getId().'/delete"]')->form(['targetId' => (string) $general->getId()]);
        $client->submit($form);
        $client->followRedirect();

        self::assertSelectorTextContains('main', 'La catégorie a été supprimée.');
        self::assertSame($general->getId(), refresh($task)->getCategory()->getId());
        self::assertCount(1, refresh($project)->getCategories());
    }

    public function testTheLastCategoryCannotBeDeleted(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $general = $project->getCategories()->first() ?: throw new \LogicException();
        $client->loginUser($owner);

        $client->request('POST', '/categories/'.$general->getId().'/delete', ['_token' => $this->boardToken($client, $project)]);
        $client->followRedirect();

        self::assertSelectorTextContains('main', 'Un projet garde au moins une catégorie.');
        self::assertCount(1, refresh($project)->getCategories());
    }

    public function testViewerCannotManageCategories(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $general = $project->getCategories()->first() ?: throw new \LogicException();
        $client->loginUser($this->member($project, ProjectRole::VIEWER));

        $crawler = $client->request('GET', '/projects/'.$project->getId());
        self::assertCount(0, $crawler->filter('form[action*="/categories"]'));

        // Forged requests (no form, so no CSRF token) are rejected; the voter is covered by ProjectVoterTest.
        $client->request('POST', '/projects/'.$project->getId().'/categories', ['name' => 'Intrus']);
        self::assertFalse($client->getResponse()->isSuccessful());
        $client->request('POST', '/categories/'.$general->getId().'/rename', ['name' => 'Intrus']);
        self::assertFalse($client->getResponse()->isSuccessful());
        self::assertSame(['Général'], refresh($project)->getCategories()->map(static fn ($category): string => $category->getName())->getValues());
    }

    public function testNonMemberCannotTouchCategories(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $general = $project->getCategories()->first() ?: throw new \LogicException();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/projects/'.$project->getId());
        self::assertResponseStatusCodeSame(404);
        $client->request('POST', '/categories/'.$general->getId().'/rename', ['name' => 'Intrus']);
        self::assertFalse($client->getResponse()->isSuccessful());
        self::assertSame('Général', refresh($general)->getName());
    }

    public function testDragAndDropMovesATaskToAnotherCategory(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        [$todo, $doing] = $project->getColumns()->toArray();
        $design = $project->addCategory('Design');
        save($project);
        $task = TaskFactory::new()->inColumn($todo)->create();
        $client->loginUser($owner);

        $this->patch($client, '/tasks/'.$task->getId().'/move', ['columnId' => $doing->getId(), 'categoryId' => $design->getId(), 'position' => 0], $this->boardToken($client, $project));

        self::assertResponseIsSuccessful();
        $task = refresh($task);
        self::assertSame([$doing->getId(), $design->getId()], [$task->getColumn()->getId(), $task->getCategory()->getId()]);
    }

    public function testATaskCannotBeMovedToACategoryOfAnotherProject(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        [$todo] = $project->getColumns()->toArray();
        $task = TaskFactory::new()->inColumn($todo)->create();
        $foreign = ProjectFactory::createOne(['owner' => $owner])->getCategories()->first() ?: throw new \LogicException();
        $client->loginUser($owner);

        $this->patch($client, '/tasks/'.$task->getId().'/move', ['categoryId' => $foreign->getId(), 'position' => 0], $this->boardToken($client, $project));

        self::assertResponseStatusCodeSame(422);
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
        self::assertNotNull($token);

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
