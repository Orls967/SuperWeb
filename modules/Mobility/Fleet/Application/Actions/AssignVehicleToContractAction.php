<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Application\Actions;

use Modules\Mobility\Fleet\Domain\Models\FleetContract;
use Modules\Mobility\Fleet\Domain\Models\FleetContractUnit;

class AssignVehicleToContractAction
{
    public function execute(int $contractId, int $vehicleId): FleetContractUnit
    {
        $alreadyAssigned = FleetContractUnit::where('vehicle_id', $vehicleId)
            ->where('status', 'ASSIGNED')
            ->exists();

        if ($alreadyAssigned) {
            throw new \LogicException('Vehicle is already assigned to an active contract');
        }

        FleetContract::where('id', $contractId)->increment('total_units');

        return FleetContractUnit::create([
            'contract_id' => $contractId,
            'vehicle_id' => $vehicleId,
            'status' => 'ASSIGNED',
            'assigned_at' => now(),
        ]);
    }
}
