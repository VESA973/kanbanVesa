<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class TaskInvitationTest extends FunctionalTestCase
{
    public function testNewcomerInvitedForATaskBySharedLinkGetsTheTask(): void
    {
        $client = self::createClient();
        [$owner, $project, $task] = $this->board();
        $client->loginUser($owner);

        $client->request('GET', '/tasks/'.$task->getId(), server: ['HTTP_TURBO_FRAME' => 'modal']);
        $client->submitForm('Inviter', ['email' => 'benevole@example.com', 'role' => 'viewer']);
        self::assertResponseRedirects('/tasks/'.$task->getId());

        $crawler = $client->request('GET', '/tasks/'.$task->getId(), server: ['HTTP_TURBO_FRAME' => 'modal']);
        $crawler = $client->submit($crawler->selectButton('🔗 Obtenir le lien')->form(), [], ['HTTP_TURBO_FRAME' => 'modal']);
        $link = (string) $crawler->filter('#invitation-link')->attr('value');
        self::assertMatchesRegularExpression('#/invitations/[a-f0-9]{64}$#', $link);
        self::assertSelectorExists('a[href^="https://wa.me/?text="]');

        // The volunteer opens the link received on WhatsApp.
        $client->request('GET', '/logout');
        $client->getCookieJar()->clear();
        $crawler = $client->request('GET', (string) parse_url($link, \PHP_URL_PATH));
        self::assertSelectorTextContains('main', 'Distribuer les flyers');
        $crawler = $client->click($crawler->filter('main')->selectLink('Créer un compte')->link());
        self::assertSame('benevole@example.com', $crawler->filter('#registration_form_email')->attr('value'), 'The address is already filled in.');

        $client->submitForm('Créer mon compte', [
            'registration_form[firstName]' => 'Bénévole',
            'registration_form[lastName]' => 'Martin',
            'registration_form[plainPassword][first]' => 'motdepasse',
            'registration_form[plainPassword][second]' => 'motdepasse',
        ]);
        $client->followRedirect();
        $client->submitForm('Rejoindre le projet');

        self::assertResponseRedirects('/my-tasks');
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Distribuer les flyers');

        $volunteer = UserFactory::repository()->findOneBy(['email' => 'benevole@example.com']) ?? throw new \LogicException();
        self::assertSame($volunteer->getId(), refresh($task)->getAssignee()?->getId());
        self::assertSame(ProjectRole::VIEWER, refresh($project)->getRoleOf($volunteer));
        self::assertFalse($volunteer->isVerified(), 'A link shared by hand does not prove the address.');
    }

    public function testOnlyTheOwnerInvitesFromATaskOrGetsALink(): void
    {
        $client = self::createClient();
        [, $project, $task] = $this->board();
        $editor = UserFactory::createOne();
        $project->addMember($editor, ProjectRole::EDITOR);
        save($project);
        $client->loginUser($editor);

        $client->request('GET', '/tasks/'.$task->getId());
        self::assertSelectorNotExists('form[action$="/invite"]');
    }

    /**
     * @return array{User, Project, Task}
     */
    private function board(): array
    {
        $owner = UserFactory::createOne();
        $project = ProjectFactory::new()->withColumns('À faire')->create(['owner' => $owner, 'name' => 'Campagne']);
        $task = TaskFactory::new()->inColumn($project->getColumns()->first() ?: throw new \LogicException())->create(['title' => 'Distribuer les flyers']);

        return [$owner, $project, $task];
    }
}
