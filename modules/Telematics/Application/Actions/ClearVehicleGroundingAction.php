<?php

declare(strict_types=1);

namespace Modules\Telematics\Application\Actions;

use Modules\Core\Contracts\Vehicle;
use Modules\Telematics\Domain\Models\TelematicsDevice;

class ClearVehicleGroundingAction
{
    public function execute(int $vehicleId, int $authorizedByUserId, string $reasonNotes): bool
    {
        if (trim($reasonNotes) === '') {
            throw new \InvalidArgumentException('Reason notes are mandatory to clear vehicle grounding.');
        }

        TelematicsDevice::where('vehicle_id', $vehicleId)->update(['status' => 'ACTIVE']);
        Vehicle::where('id', $vehicleId)->update(['status' => 'active']);

        return true;
    }
}
