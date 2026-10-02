<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Models\Truck;

class CompleteFleetMaintenanceAction
{
    /**
     * Mark fleet maintenance completed and return truck to AVAILABLE.
     */
    public function execute(Truck $truck): Truck
    {
        $truck->status = FleetStatus::AVAILABLE;
        $truck->save();

        return $truck->fresh();
    }
}
