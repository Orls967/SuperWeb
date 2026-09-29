<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Core\Domain\Events\VehicleAcquired;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Shared\Application\BaseAction;

class AcquireVehicleAction extends BaseAction
{
    public function handle(
        User|int $user,
        Car|int $car,
        ?string $plateNumber = null,
        ?string $color = null,
        ?string $vin = null,
        int $odometerKm = 0,
        ?string $acquiredViaType = 'manual',
        ?int $acquiredViaId = null,
    ): Vehicle {
        $userId = $user instanceof User ? $user->id : $user;
        $carId = $car instanceof Car ? $car->id : $car;

        return $this->transaction(function () use (
            $userId,
            $carId,
            $plateNumber,
            $color,
            $vin,
            $odometerKm,
            $acquiredViaType,
            $acquiredViaId
        ) {
            $vehicle = Vehicle::create([
                'user_id' => $userId,
                'car_id' => $carId,
                'plate_number' => $plateNumber ? strtoupper(trim($plateNumber)) : null,
                'color' => $color,
                'vin' => $vin,
                'odometer_km' => $odometerKm,
                'acquired_at' => now(),
                'acquired_via_type' => $acquiredViaType,
                'acquired_via_id' => $acquiredViaId,
                'status' => 'active',
            ]);

            // Auto-remove from wishlist if present
            DB::table('dex_wishlists')
                ->where('user_id', $userId)
                ->where('car_id', $carId)
                ->delete();

            event(new VehicleAcquired(
                vehicle: $vehicle,
                actorId: $userId,
                method: $acquiredViaType ?? 'manual'
            ));

            return $vehicle;
        });
    }
}
