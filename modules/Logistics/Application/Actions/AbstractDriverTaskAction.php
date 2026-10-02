<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOperationException;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Shipment;

abstract class AbstractDriverTaskAction
{
    protected function assertAssignedTo(Shipment $shipment, Driver $driver): void
    {
        if ((int) $shipment->driver_id !== $driver->id) {
            throw new InvalidDeliveryOperationException("Resi {$shipment->tracking_number} tidak ditugaskan kepada Anda.");
        }
    }
}
