<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;

final class SearchTest extends FunctionalTestCase
{
    public function testFindsTasksAndProjectsOfMyProjectsOnly(): void
    {
        $client = self::createClient();
        $me = UserFactory::createOne();
        $mine = ProjectFactory::new()->withColumns('À faire')->create(['name' => 'Refonte du site', 'owner' => $me]);
        $secret = ProjectFactory::new()->withColumns('À faire')->create(['name' => 'Projet secret']);
        TaskFactory::new()->inColumn($mine->getColumns()->first() ?: throw new \LogicException())->create(['title' => 'Écrire la page Société']);
        TaskFactory::new()->inColumn($secret->getColumns()->first() ?: throw new \LogicException())->create(['title' => 'Société confidentielle']);
        $client->loginUser($me);

        $client->request('GET', '/search?q=societe');

        self::assertSelectorTextContains('#results-tasks + ul', 'Écrire la page Société', 'Case and accent insensitive.');
        self::assertSelectorTextNotContains('main', 'confidentielle');

        $client->request('GET', '/search?q=refonte');
        self::assertSelectorTextContains('#results-projects + ul', 'Refonte du site');
    }

    public function testLikeWildcardsAreSearchedLiterally(): void
    {
        $client = self::createClient();
        $me = UserFactory::createOne();
        $project = ProjectFactory::new()->withColumns('À faire')->create(['owner' => $me]);
        TaskFactory::new()->inColumn($project->getColumns()->first() ?: throw new \LogicException())->create(['title' => 'Une tâche ordinaire']);
        $client->loginUser($me);

        $client->request('GET', '/search?q=%25%25');

        self::assertSelectorTextContains('main', 'Aucun résultat');
    }

    public function testTooShortQueryDoesNotSearch(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/search?q=a');

        self::assertSelectorTextContains('main', 'au moins 2 caractères');
    }
}
