<?php

declare(strict_types=1);

namespace Modules\Mobility\Telematics\Application\Services;

use Modules\Core\Contracts\Vehicle;
use Modules\Mobility\Telematics\Contracts\VehicleDispatchEligibilityContract;
use Modules\Mobility\Telematics\Domain\Models\TelematicsDevice;

class VehicleDispatchEligibilityService implements VehicleDispatchEligibilityContract
{
    public function isEligibleForDispatch(int $vehicleId): bool
    {
        $vehicle = Vehicle::find($vehicleId);
        if ($vehicle !== null && strtolower((string) $vehicle->status) === 'grounded') {
            return false;
        }

        $device = TelematicsDevice::where('vehicle_id', $vehicleId)->first();
        if ($device !== null && strtoupper((string) $device->status) === 'GROUNDED') {
            return false;
        }

        return true;
    }
}
