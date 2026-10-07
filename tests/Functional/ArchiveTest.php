<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;

use function Zenstruck\Foundry\force;
use function Zenstruck\Foundry\Persistence\refresh;

final class ArchiveTest extends FunctionalTestCase
{
    public function testOwnerArchivesAndUnarchivesAProject(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $project = ProjectFactory::new()->withColumns('À faire')->create(['name' => 'Ancien site', 'owner' => $owner]);
        $column = $project->getColumns()->first() ?: throw new \LogicException();
        TaskFactory::new()->inColumn($column)->create(['title' => 'Tâche archivée', 'assignee' => force($owner)]);
        $client->loginUser($owner);

        $client->request('GET', '/projects/'.$project->getId().'/edit');
        $client->submitForm('Archiver le projet');
        self::assertResponseRedirects('/projects');
        self::assertTrue(refresh($project)->isArchived());

        $client->followRedirect();
        self::assertSelectorCount(0, 'main article');
        self::assertSelectorTextContains('details', 'Ancien site');
        $client->request('GET', '/my-tasks');
        self::assertSelectorTextNotContains('main', 'Tâche archivée');

        $client->request('GET', '/projects/'.$project->getId());
        self::assertSelectorTextContains('main [role=status]', 'archivé');
        self::assertSelectorNotExists('form[action$="/tasks"]', 'An archived board is read-only.');
        self::assertSelectorNotExists('[data-controller~="sortable"]');

        $client->submitForm('Désarchiver');
        self::assertResponseRedirects('/projects/'.$project->getId());
        self::assertFalse(refresh($project)->isArchived());
    }

    public function testArchivedProjectCannotBeModified(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $project = ProjectFactory::new()->withColumns('À faire')->create(['owner' => $owner]);
        $task = TaskFactory::new()->inColumn($project->getColumns()->first() ?: throw new \LogicException())->create(['title' => 'Figée']);
        refresh($project)->archive();
        self::getContainer()->get('doctrine')->getManager()->flush();
        $client->loginUser($owner);

        $client->request('GET', '/projects/'.$project->getId().'/edit');
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/tasks/'.$task->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="task_form"]');
        self::assertSelectorNotExists('form[action$="/comments"]');
    }
}
