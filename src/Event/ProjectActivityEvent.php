<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Project;
use App\Enum\ActivityAction;

/**
 * Dispatched by the business services before they flush, so listeners
 * (activity log, notifications…) take part in the same transaction.
 */
final readonly class ProjectActivityEvent
{
    /**
     * @param array<string, string> $payload extra values for the log message (column, assignee…)
     */
    public function __construct(
        public Project $project,
        public ActivityAction $action,
        public string $subject,
        public array $payload = [],
    ) {
    }
}
