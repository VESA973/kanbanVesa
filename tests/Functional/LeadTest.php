<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\BoardColumn;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use App\Service\ProgramMembership;

use function Zenstruck\Foundry\force;
use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class LeadTest extends FunctionalTestCase
{
    public function testOwnerAppointsAResponsableShownOnTheBoard(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $alex = $this->member($project, ProjectRole::EDITOR, 'Alex', 'Martin');
        $member = $project->getMemberOf($alex) ?? throw new \LogicException();
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/projects/'.$project->getId().'/members');
        $client->submit($crawler->filter('form[action="/members/'.$member->getId().'/lead"]')->form());

        self::assertTrue(refresh($member)->isLead());
        $client->request('GET', '/projects/'.$project->getId());
        self::assertSelectorTextContains('h1 + p', 'Responsable : Alex Martin');

        $crawler = $client->request('GET', '/projects/'.$project->getId().'/members');
        $client->submit($crawler->filter('form[action="/members/'.$member->getId().'/lead"]')->form());
        self::assertFalse(refresh($member)->isLead());
    }

    public function testAnEditorCannotAppointResponsables(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $editor = $this->member($project, ProjectRole::EDITOR);
        $member = $project->getMemberOf($editor) ?? throw new \LogicException();
        $client->loginUser($editor);

        $client->request('GET', '/projects/'.$project->getId().'/members');
        self::assertSelectorNotExists('form[action$="/lead"]');

        $client->request('POST', '/members/'.$member->getId().'/lead');
        self::assertFalse($client->getResponse()->isSuccessful());
        self::assertFalse(refresh($member)->isLead());
    }

    public function testMyTasksListsTheRemainingTasksOfTheChantiersILead(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        [, $elsewhere] = $this->project();
        $alex = $this->member($project, ProjectRole::VIEWER);
        $elsewhere->addMember($alex, ProjectRole::EDITOR);
        save($elsewhere);
        $this->appoint($project, $alex);
        TaskFactory::new()->inColumn($this->column($project))->assignedTo($owner)->create(['title' => 'Reste à faire']);
        TaskFactory::new()->inColumn($this->column($project))->create(['title' => 'Déjà faite', 'completedAt' => force(new \DateTimeImmutable())]);
        TaskFactory::new()->inColumn($this->column($elsewhere))->create(['title' => 'Ailleurs']);
        $client->loginUser($alex);

        $client->request('GET', '/my-tasks');

        self::assertSelectorTextContains('#led-heading + p + div', $project->getName());
        self::assertSelectorTextContains('#led-heading + p + div', '1 tâche restante');
        self::assertSelectorTextContains('#led-heading + p + div', 'Reste à faire');
        self::assertSelectorTextNotContains('main', 'Déjà faite');
        self::assertSelectorTextNotContains('main', 'Ailleurs');
    }

    public function testAProgramResponsableFollowsAllItsChantiers(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $program = ProgramFactory::createOne(['owner' => $owner]);
        $voirie = ProjectFactory::new()->inProgram($program)->withColumns('À faire')->create(['owner' => $owner, 'name' => 'Voirie']);
        $ecole = ProjectFactory::new()->inProgram($program)->withColumns('À faire')->create(['owner' => $owner, 'name' => 'École']);
        TaskFactory::new()->inColumn($this->column($voirie))->create(['title' => 'Refaire le trottoir']);
        TaskFactory::new()->inColumn($this->column($ecole))->create(['title' => 'Peindre la classe']);
        $alex = UserFactory::createOne();
        $member = self::getContainer()->get(ProgramMembership::class)->join(refresh($program), $alex, ProjectRole::VIEWER);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/programs/'.$program->getId().'/members');
        $client->submit($crawler->filter('form[action="/program-members/'.$member->getId().'/lead"]')->form());
        $client->loginUser($alex);
        $client->request('GET', '/my-tasks');

        self::assertSelectorTextContains('#led-heading + p + div', 'Refaire le trottoir');
        self::assertSelectorTextContains('#led-heading + p + div', 'Peindre la classe');
    }

    public function testNoResponsibilityNoSection(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $alex = $this->member($project, ProjectRole::EDITOR);
        TaskFactory::new()->inColumn($this->column($project))->create();
        $client->loginUser($alex);

        $client->request('GET', '/my-tasks');

        self::assertSelectorNotExists('#led-heading');
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
        return $project->getColumns()->first() ?: throw new \LogicException('The project has no column.');
    }

    private function member(Project $project, ProjectRole $role, ?string $firstName = null, ?string $lastName = null): User
    {
        $user = UserFactory::createOne(array_filter(['firstName' => $firstName, 'lastName' => $lastName]));
        $project->addMember($user, $role);
        save($project);

        return $user;
    }

    private function appoint(Project $project, User $user): void
    {
        ($project->getMemberOf($user) ?? throw new \LogicException())->toggleLead();
        save($project);
    }
}
