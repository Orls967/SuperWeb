<?php

declare(strict_types=1);

namespace Modules\Telematics\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VehicleAnomalyDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $vehicleId,
        public readonly string $anomalyType,
        public readonly string $severity,
        public readonly array $details
    ) {}
}
