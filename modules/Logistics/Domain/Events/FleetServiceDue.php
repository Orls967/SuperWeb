<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a truck's trip odometer exceeds the next service interval.
 *
 * AutoServe listens to create a maintenance booking.
 */
class FleetServiceDue
{
    use Dispatchable;

    public function __construct(
        public readonly int $vehicleId,
        public readonly int $truckId,
        public readonly int $odometerKm,
        public readonly int $serviceIntervalKm,
    ) {}
}
