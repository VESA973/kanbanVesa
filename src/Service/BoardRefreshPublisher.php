<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Tells the browsers showing a board to refresh it (Turbo 8 morphs the page).
 * Updates are private: only members, who received a subscription cookie with
 * the board, get them. The payload carries no project data anyway.
 */
final readonly class BoardRefreshPublisher
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public static function topicFor(Project|int $project): string
    {
        $id = $project instanceof Project ? $project->getId() : $project;

        return \sprintf('project-%d', $id);
    }

    /**
     * @param string|null $requestId Turbo ignores a refresh carrying the id of one of its own requests
     */
    public function publish(int $projectId, ?string $requestId = null): void
    {
        $requestAttribute = null === $requestId ? '' : \sprintf(' request-id="%s"', htmlspecialchars($requestId, \ENT_QUOTES));

        try {
            $this->hub->publish(new Update(
                self::topicFor($projectId),
                \sprintf('<turbo-stream action="refresh"%s></turbo-stream>', $requestAttribute),
                private: true,
            ));
        } catch (\Throwable $exception) {
            // Real time is a convenience: an unreachable hub must never break the application.
            $this->logger->warning('Board refresh could not be published to Mercure.', ['project' => $projectId, 'exception' => $exception]);
        }
    }
}
