<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use Symfony\Component\DomCrawler\Crawler;

use function Zenstruck\Foundry\force;
use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class BulkAssignTest extends FunctionalTestCase
{
    public function testEditorAssignsSeveralMembersToAllRemainingTasks(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $editor = $this->member($project, ProjectRole::EDITOR);
        [$todo, $done] = $project->getColumns()->getValues();
        $open = TaskFactory::new()->inColumn($todo)->assignedTo($owner)->create();
        $other = TaskFactory::new()->inColumn($done)->create();
        $finished = TaskFactory::new()->inColumn($done)->create(['completedAt' => force(new \DateTimeImmutable())]);
        $client->loginUser($editor);

        $crawler = $client->request('GET', '/projects/'.$project->getId());
        $crawler = $client->click($crawler->selectLink('Assigner en masse')->link());
        $client->submitForm('Assigner', [
            $this->field($crawler, $owner) => (string) $owner->getId(),
            $this->field($crawler, $editor) => (string) $editor->getId(),
        ]);

        self::assertResponseRedirects('/projects/'.$project->getId());
        self::assertCount(2, refresh($open)->getAssignees());
        self::assertCount(2, refresh($other)->getAssignees());
        self::assertTrue(refresh($finished)->getAssignees()->isEmpty());
        $client->loginUser($owner);
        $client->request('GET', '/projects/'.$project->getId().'/activity');
        self::assertSelectorTextContains('ol', 'a assigné 2 tâche(s) restante(s)');
    }

    public function testAssignmentCanBeLimitedToOneColumn(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        [$todo, $done] = $project->getColumns()->getValues();
        $inTodo = TaskFactory::new()->inColumn($todo)->create();
        $inDone = TaskFactory::new()->inColumn($done)->create();
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/projects/'.$project->getId().'/bulk-assign');
        $client->submitForm('Assigner', [
            $this->field($crawler, $owner) => (string) $owner->getId(),
            'bulk_assign_form[column]' => (string) $todo->getId(),
        ]);

        self::assertTrue(refresh($inTodo)->isAssignedTo($owner));
        self::assertTrue(refresh($inDone)->getAssignees()->isEmpty());
    }

    public function testChoosingNobodyIsRejected(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $client->loginUser($owner);

        $client->request('GET', '/projects/'.$project->getId().'/bulk-assign');
        $client->submitForm('Assigner');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Choisissez au moins une personne.');
    }

    public function testViewerCannotBulkAssign(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $viewer = $this->member($project, ProjectRole::VIEWER);
        $client->loginUser($viewer);

        $client->request('GET', '/projects/'.$project->getId());
        self::assertSelectorNotExists('a[href$="/bulk-assign"]');
        $client->request('GET', '/projects/'.$project->getId().'/bulk-assign');
        self::assertResponseStatusCodeSame(403);
    }

    public function testNonMemberGetsA404(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/projects/'.$project->getId().'/bulk-assign');

        self::assertResponseStatusCodeSame(404);
    }

    private function field(Crawler $crawler, User $user): string
    {
        $values = $crawler->filter('input[name="bulk_assign_form[assignees][]"]')->extract(['value']);

        return \sprintf('bulk_assign_form[assignees][%d]', array_search((string) $user->getId(), $values, true));
    }

    /**
     * @return array{User, Project}
     */
    private function project(): array
    {
        $owner = UserFactory::createOne();

        return [$owner, ProjectFactory::new()->withColumns('À faire', 'Terminé')->create(['owner' => $owner])];
    }

    private function member(Project $project, ProjectRole $role): User
    {
        $user = UserFactory::createOne();
        $project->addMember($user, $role);
        save($project);

        return $user;
    }
}
