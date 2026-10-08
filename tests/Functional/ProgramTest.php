<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Program;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use App\Repository\ProgramRepository;
use App\Repository\ProjectRepository;
use App\Service\ProgramMembership;

use function Zenstruck\Foundry\force;
use function Zenstruck\Foundry\Persistence\refresh;

final class ProgramTest extends FunctionalTestCase
{
    public function testOwnerCreatesAProgramThenAProjectInsideIt(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $client->loginUser($owner);

        $client->request('GET', '/programs/new');
        $client->submitForm('Créer le projet global', ['project_form[name]' => 'Mairie 2027']);
        $program = self::getContainer()->get(ProgramRepository::class)->findOneBy(['name' => 'Mairie 2027']) ?? throw new \LogicException();
        self::assertResponseRedirects('/programs/'.$program->getId());

        $crawler = $client->followRedirect();
        $client->click($crawler->selectLink('Nouveau projet')->link());
        self::assertSelectorExists('select[name="project_form[program]"] option[value="'.$program->getId().'"][selected]');
        $client->submitForm('Créer le projet', ['project_form[name]' => 'Voirie']);
        $client->followRedirect();

        $project = self::getContainer()->get(ProjectRepository::class)->findOneBy(['name' => 'Voirie']) ?? throw new \LogicException();
        self::assertSame($program->getId(), $project->getProgram()->getId());
        $client->request('GET', '/programs/'.$program->getId());
        self::assertSelectorTextContains('main ul', 'Voirie');
    }

    public function testAProjectCreatedWithoutChoiceGoesToTheDefaultProgram(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $client->loginUser($owner);

        $client->request('GET', '/projects/new');
        $client->submitForm('Créer le projet', ['project_form[name]' => 'Kanban']);

        $project = self::getContainer()->get(ProjectRepository::class)->findOneBy(['name' => 'Kanban']) ?? throw new \LogicException();
        self::assertSame('Général', $project->getProgram()->getName());
        self::assertSame($owner->getId(), $project->getProgram()->getOwner()->getId());
    }

    public function testTheProgramPageShowsTheProgressOfEachProjectAndOfTheWhole(): void
    {
        $client = self::createClient();
        [$owner, $program] = $this->program();
        $roads = ProjectFactory::new()->inProgram($program)->withColumns('À faire')->create(['name' => 'Voirie', 'owner' => $owner]);
        $schools = ProjectFactory::new()->inProgram($program)->withColumns('À faire')->create(['name' => 'Écoles', 'owner' => $owner]);
        ProjectFactory::new()->inProgram($program)->create(['name' => 'Culture', 'owner' => $owner]);
        $roadsColumn = $roads->getColumns()->first() ?: throw new \LogicException();
        $schoolsColumn = $schools->getColumns()->first() ?: throw new \LogicException();
        TaskFactory::new()->inColumn($roadsColumn)->many(3)->create(['completedAt' => force(new \DateTimeImmutable())]);
        TaskFactory::new()->inColumn($roadsColumn)->create();
        TaskFactory::new()->inColumn($schoolsColumn)->create();
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/programs/'.$program->getId());

        // 3 done out of 5 tasks: 60 %, not the average of 75 % and 0 %.
        self::assertSame('60', $crawler->filter('#overall-progress-title + div [role="progressbar"]')->attr('aria-valuenow'));
        $cards = $crawler->filter('main ul article');
        self::assertSame(['Culture', 'Écoles', 'Voirie'], $cards->each(static fn ($card): string => trim($card->filter('h2')->text())));
        self::assertStringContainsString('0 tâche', $cards->eq(0)->text());
        self::assertSame('75', $cards->eq(2)->filter('[role="progressbar"]')->attr('aria-valuenow'));
    }

    public function testAMemberInvitedToTheProgramSeesAllItsProjectsEvenFutureOnes(): void
    {
        $client = self::createClient();
        [$owner, $program] = $this->program();
        $roads = ProjectFactory::new()->inProgram($program)->create(['name' => 'Voirie', 'owner' => $owner]);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/programs/'.$program->getId().'/members');
        $client->submit($crawler->selectButton("Envoyer l'invitation")->form(['invitation_form[email]' => 'alex@example.com', 'invitation_form[role]' => 'editor']));
        self::assertResponseRedirects('/programs/'.$program->getId().'/members');
        $link = (string) parse_url(self::extractLink(self::getMailerMessage()), \PHP_URL_PATH);

        $alex = UserFactory::createOne(['email' => 'alex@example.com']);
        $client->loginUser($alex);
        $client->request('GET', $link);
        self::assertSelectorTextContains('main', 'projet global « Mairie 2027 »');
        $client->submitForm('Rejoindre le projet');
        self::assertResponseRedirects('/programs/'.$program->getId());

        self::assertSame(ProjectRole::EDITOR, refresh($roads)->getRoleOf($alex));
        $client->request('GET', '/projects/'.$roads->getId());
        self::assertResponseIsSuccessful();

        // A project created afterwards by the owner is shared too.
        $client->loginUser($owner);
        $client->request('GET', '/projects/new?program='.$program->getId());
        $client->submitForm('Créer le projet', ['project_form[name]' => 'Écoles']);
        $schools = self::getContainer()->get(ProjectRepository::class)->findOneBy(['name' => 'Écoles']) ?? throw new \LogicException();
        self::assertSame(ProjectRole::EDITOR, $schools->getRoleOf($alex));
    }

    public function testRemovingAProgramMemberRemovesTheirInheritedAccess(): void
    {
        $client = self::createClient();
        [$owner, $program] = $this->program();
        $roads = ProjectFactory::new()->inProgram($program)->create(['name' => 'Voirie', 'owner' => $owner]);
        $alex = $this->joinProgram($program, ProjectRole::VIEWER);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/projects/'.$roads->getId().'/members');
        self::assertSelectorTextContains('main', 'via le projet global');
        self::assertCount(0, $crawler->filter('form[action$="/remove"]'), 'Inherited access is managed from the program.');

        $crawler = $client->request('GET', '/programs/'.$program->getId().'/members');
        $client->submit($crawler->filter('form[action$="/remove"]')->form());
        self::assertResponseRedirects('/programs/'.$program->getId().'/members');

        self::assertNull(refresh($roads)->getRoleOf($alex));
    }

    public function testProjectsAreGroupedByProgramOnMyProjects(): void
    {
        $client = self::createClient();
        [$owner, $program] = $this->program();
        ProjectFactory::new()->inProgram($program)->create(['name' => 'Voirie', 'owner' => $owner]);
        ProjectFactory::createOne(['name' => 'Perso', 'owner' => $owner]);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/projects');

        $sections = $crawler->filter('main section[aria-labelledby^="program-"]');
        self::assertSame(['Général', 'Mairie 2027'], $sections->each(static fn ($section): string => trim($section->filter('h2')->text())));
        self::assertStringContainsString('Voirie', $sections->eq(1)->text());
        self::assertStringContainsString('Perso', $sections->eq(0)->text());
    }

    public function testViewerCannotCreateProjectsAndNonMemberGetsA404(): void
    {
        $client = self::createClient();
        [, $program] = $this->program();
        $viewer = $this->joinProgram($program, ProjectRole::VIEWER);
        $client->loginUser($viewer);

        $client->request('GET', '/programs/'.$program->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href^="/projects/new"]');
        $crawler = $client->request('GET', '/projects/new?program='.$program->getId());
        self::assertCount(0, $crawler->filter('select[name="project_form[program]"] option[value="'.$program->getId().'"]'));

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/programs/'.$program->getId());
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/programs/'.$program->getId().'/members');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAProgramWithProjectsCannotBeDeleted(): void
    {
        $client = self::createClient();
        [$owner, $program] = $this->program();
        ProjectFactory::new()->inProgram($program)->create(['owner' => $owner]);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/programs/'.$program->getId().'/edit');

        self::assertCount(0, $crawler->filter('form[action$="/delete"]'));
        self::assertSelectorTextContains('main', 'contient encore des projets');
    }

    /**
     * @return array{User, Program}
     */
    private function program(): array
    {
        $owner = UserFactory::createOne();

        return [$owner, ProgramFactory::createOne(['name' => 'Mairie 2027', 'owner' => $owner])];
    }

    private function joinProgram(Program $program, ProjectRole $role): User
    {
        $user = UserFactory::createOne();
        self::getContainer()->get(ProgramMembership::class)->join(refresh($program), $user, $role);

        return $user;
    }
}
