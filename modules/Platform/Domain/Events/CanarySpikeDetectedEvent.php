<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Events;

use Modules\Platform\Domain\Models\CanaryObservation;
use Modules\Platform\Domain\Models\ChangeDeployment;

final class CanarySpikeDetectedEvent
{
    public function __construct(
        public readonly ChangeDeployment $deployment,
        public readonly CanaryObservation $observation
    ) {}
}
