<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EvSessionCompletedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $sessionId,
        public readonly int $finalSohBps
    ) {}
}
