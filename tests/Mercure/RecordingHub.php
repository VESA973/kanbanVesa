<?php

declare(strict_types=1);

namespace App\Tests\Mercure;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

/**
 * Replaces the Mercure hub in the test environment: no network, updates kept for assertions.
 */
final class RecordingHub implements HubInterface
{
    /** @var list<Update> */
    public array $updates = [];

    public function getPublicUrl(): string
    {
        return 'http://localhost/.well-known/mercure';
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    public function publish(Update $update): string
    {
        $this->updates[] = $update;

        return 'urn:uuid:test-'.\count($this->updates);
    }

    public function getProtocolVersion(): ProtocolVersion
    {
        return ProtocolVersion::Legacy;
    }

    public function getCookieName(): string
    {
        return 'mercureAuthorization';
    }

    /**
     * @return list<string>
     */
    public function topics(): array
    {
        $topics = [];
        foreach ($this->updates as $update) {
            foreach ($update->getTopics() as $topic) {
                if (\is_string($topic)) {
                    $topics[] = $topic;
                }
            }
        }

        return $topics;
    }
}
