<?php

declare(strict_types=1);

namespace Modules\Mobility\Telematics\Contracts;

interface VehicleDispatchEligibilityContract
{
    public function isEligibleForDispatch(int $vehicleId): bool;
}
