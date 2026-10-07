<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use App\Tests\Mercure\RecordingHub;

final class RealtimeTest extends FunctionalTestCase
{
    public function testBoardSubscribesToItsPrivateTopic(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $project = ProjectFactory::new()->withColumns('À faire')->create(['owner' => $owner]);
        $client->loginUser($owner);

        $client->request('GET', '/projects/'.$project->getId());

        self::assertSelectorExists('turbo-stream-source[src*="project-'.$project->getId().'"]');
        self::assertBrowserHasCookie('mercureAuthorization', '/.well-known/mercure');
    }

    public function testChangingTheBoardAsksOtherBrowsersToRefresh(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $project = ProjectFactory::new()->withColumns('À faire')->create(['owner' => $owner]);
        $task = TaskFactory::new()->inColumn($project->getColumns()->first() ?: throw new \LogicException())->create();
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/tasks/'.$task->getId());

        $client->submit($crawler->selectButton('Commenter')->form(['content' => 'Hop']), [], ['HTTP_X_TURBO_REQUEST_ID' => 'req-1']);

        $hub = $this->recordingHub();
        self::assertSame(['project-'.$project->getId()], $hub->topics());
        self::assertStringContainsString('request-id="req-1"', $hub->updates[0]->getData());
    }

    public function testReadingTheBoardPublishesNothing(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $project = ProjectFactory::new()->withColumns('À faire')->create(['owner' => $owner]);
        $client->loginUser($owner);

        $client->request('GET', '/projects/'.$project->getId());

        self::assertSame([], $this->recordingHub()->updates);
    }

    private function recordingHub(): RecordingHub
    {
        $hub = self::getContainer()->get(RecordingHub::class);
        self::assertInstanceOf(RecordingHub::class, $hub);

        return $hub;
    }
}
