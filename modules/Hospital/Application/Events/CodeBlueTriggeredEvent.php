<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Events;

use Modules\Hospital\Domain\Models\CodeBlueEvent;

final class CodeBlueTriggeredEvent
{
    public function __construct(
        public readonly CodeBlueEvent $codeBlueEvent,
        public readonly int $actorId
    ) {}
}
