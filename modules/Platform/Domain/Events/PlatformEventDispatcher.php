<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Events;

use Illuminate\Support\Facades\Event;

/**
 * Dispatcher event terpadu domain Platform ke event bus Laravel dan outbox spine.
 */
class PlatformEventDispatcher
{
    /**
     * @param object $event
     */
    public function dispatch(object $event): void
    {
        Event::dispatch($event);
    }
}

