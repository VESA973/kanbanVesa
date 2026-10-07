<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\BoardRefreshPublisher;
use App\Tests\Mercure\RecordingHub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class RealtimeBoardTest extends TestCase
{
    public function testPublishesAPrivateTurboRefreshForTheProject(): void
    {
        $hub = new RecordingHub();

        new BoardRefreshPublisher($hub, $this->createStub(LoggerInterface::class))->publish(42, 'abc"123');

        self::assertCount(1, $hub->updates);
        self::assertSame(['project-42'], $hub->updates[0]->getTopics());
        self::assertTrue($hub->updates[0]->isPrivate());
        self::assertSame('<turbo-stream action="refresh" request-id="abc&quot;123"></turbo-stream>', $hub->updates[0]->getData());
    }

    public function testAnUnreachableHubIsOnlyLogged(): void
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('publish')->willThrowException(new \RuntimeException('Connection refused'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        new BoardRefreshPublisher($hub, $logger)->publish(42);
    }

    public function testTopicIsDerivedFromTheProjectId(): void
    {
        self::assertSame('project-7', BoardRefreshPublisher::topicFor(7));
        self::assertInstanceOf(Update::class, new Update(BoardRefreshPublisher::topicFor(7)));
    }
}
