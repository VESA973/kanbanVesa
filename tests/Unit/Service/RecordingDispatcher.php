<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\ActivityAction;
use App\Event\ProjectActivityEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps the dispatched events so tests can check what would be logged.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    /**
     * @return list<ActivityAction>
     */
    public function actions(): array
    {
        $actions = [];
        foreach ($this->events as $event) {
            if ($event instanceof ProjectActivityEvent) {
                $actions[] = $event->action;
            }
        }

        return $actions;
    }

    public function last(): ProjectActivityEvent
    {
        $event = end($this->events);
        if (!$event instanceof ProjectActivityEvent) {
            throw new \LogicException('No project activity was dispatched.');
        }

        return $event;
    }
}
