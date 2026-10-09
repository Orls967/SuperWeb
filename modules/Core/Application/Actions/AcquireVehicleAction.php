<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Core\Domain\Events\VehicleAcquired;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Shared\Application\BaseAction;

class AcquireVehicleAction extends BaseAction implements AcquiresVehicle
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
    ): Vehicle {
        $userId = $user instanceof User ? $user->id : $user;
        $carId = is_object($car) ? ($car->id ?? null) : $car;

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

            $this->audit(
                action: 'core.vehicle.acquired',
                auditable: $vehicle,
                oldValues: null,
                newValues: [
                    'user_id' => $userId,
                    'car_id' => $carId,
                    'plate_number' => $vehicle->plate_number,
                    'status' => 'active',
                ],
                context: [
                    'acquired_via_type' => $acquiredViaType,
                    'acquired_via_id' => $acquiredViaId,
                ],
                correlationId: 'veh_acq_'.$vehicle->id.'_'.$userId,
                impactType: 'ownership',
            );

            // Defer until after commit: listeners write to the Vehicle Passport
            // hash chain and must never observe uncommitted vehicle state.
            DB::afterCommit(fn () => event(new VehicleAcquired(
                vehicle: $vehicle,
                actorId: $userId,
                method: $acquiredViaType ?? 'manual'
            )));

            return $vehicle;
        });
    }
}
