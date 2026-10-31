<?php

declare(strict_types=1);

namespace Modules\Telematics\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelematicsAnomalyDetectedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $vehicleId,
        public readonly string $metric,
        public readonly int $observedValue,
        public readonly int $thresholdValue,
        public readonly string $anomalyDate
    ) {}
}
