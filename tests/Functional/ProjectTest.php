<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\ProjectRole;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;

use function Zenstruck\Foundry\Persistence\refresh;

final class ProjectTest extends FunctionalTestCase
{
    public function testHomeRedirectsToMyProjects(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/');

        self::assertResponseRedirects('/projects');
    }

    public function testUserCreatesAProjectAndBecomesItsOwner(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);
        $client->request('GET', '/projects/new');

        $client->submitForm('Créer le projet', [
            'project_form[name]' => 'Refonte du site 🚀',
            'project_form[description]' => 'Nouvelle charte graphique',
            'project_form[color]' => 'emerald',
        ]);

        $project = ProjectFactory::repository()->findOneBy(['name' => 'Refonte du site 🚀']);
        self::assertNotNull($project);
        self::assertSame(ProjectRole::OWNER, $project->getRoleOf($user));
        self::assertResponseRedirects('/projects/'.$project->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Refonte du site 🚀');
    }

    public function testProjectNameIsRequired(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/projects/new');

        $client->submitForm('Créer le projet', ['project_form[name]' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, ProjectFactory::repository()->count());
    }

    public function testMyProjectsListsOnlyProjectsIAmAMemberOf(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        ProjectFactory::createOne(['name' => 'Mon projet', 'owner' => $user]);
        ProjectFactory::new()->withMember($user, ProjectRole::VIEWER)->create(['name' => 'Projet partagé']);
        ProjectFactory::createOne(['name' => 'Projet secret']);
        $client->loginUser($user);

        $client->request('GET', '/projects');

        self::assertSelectorCount(2, 'main article');
        self::assertAnySelectorTextContains('main article', 'Mon projet');
        self::assertAnySelectorTextContains('main article', 'Projet partagé');
        self::assertAnySelectorTextNotContains('main article', 'Projet secret');
    }

    public function testNonMemberGetsA404(): void
    {
        $client = self::createClient();
        $project = ProjectFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        foreach (['', '/edit'] as $suffix) {
            $client->request('GET', '/projects/'.$project->getId().$suffix);
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testViewerSeesTheProjectButCannotEditIt(): void
    {
        $client = self::createClient();
        $viewer = UserFactory::createOne();
        $project = ProjectFactory::new()->withMember($viewer, ProjectRole::VIEWER)->create();
        $client->loginUser($viewer);

        $client->request('GET', '/projects/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href$="/edit"]');

        $client->request('GET', '/projects/'.$project->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerEditsTheProject(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $project = ProjectFactory::createOne(['name' => 'Ancien nom', 'owner' => $owner]);
        $client->loginUser($owner);
        $client->request('GET', '/projects/'.$project->getId().'/edit');

        $client->submitForm('Enregistrer', ['project_form[name]' => 'Nouveau nom']);

        self::assertResponseRedirects('/projects/'.$project->getId());
        self::assertSame('Nouveau nom', refresh($project)->getName());
    }

    public function testOwnerDeletesTheProject(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $project = ProjectFactory::createOne(['owner' => $owner]);
        $client->loginUser($owner);
        $client->request('GET', '/projects/'.$project->getId().'/edit');

        $client->submitForm('Supprimer le projet');

        self::assertResponseRedirects('/projects');
        self::assertSame(0, ProjectFactory::repository()->count());
    }

    /**
     * The editor never sees the delete form, so a forged request is rejected
     * (invalid CSRF token, then the voter) and the project survives.
     */
    public function testEditorCannotDeleteTheProject(): void
    {
        $client = self::createClient();
        $editor = UserFactory::createOne();
        $project = ProjectFactory::new()->withMember($editor, ProjectRole::EDITOR)->create();
        $client->loginUser($editor);

        $client->request('POST', '/projects/'.$project->getId().'/delete');

        self::assertFalse($client->getResponse()->isSuccessful());
        self::assertSame(1, ProjectFactory::repository()->count());
    }
}
