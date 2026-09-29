<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use App\Models\User;
use Modules\Core\Domain\Models\Vehicle;

interface AcquiresVehicle
{
    public function handle(
        User|int $user,
        object|int $car,
        ?string $plateNumber = null,
        ?string $color = null,
        ?string $vin = null,
        int $odometerKm = 0,
        ?string $acquiredViaType = 'manual',
        ?int $acquiredViaId = null,
    ): Vehicle;
}
