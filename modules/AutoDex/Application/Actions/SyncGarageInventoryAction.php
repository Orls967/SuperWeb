<?php

declare(strict_types=1);

namespace Modules\AutoDex\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Shared\Application\BaseAction;

class SyncGarageInventoryAction extends BaseAction
{
    public function __construct(
        protected AcquiresVehicle $acquiresVehicle
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $userId = (int) $params['user_id'];
            $carId = (int) $params['car_id'];
            $plateNumber = strtoupper(trim((string) $params['plate_number']));
            $color = (string) ($params['color'] ?? 'Black');
            $odometerKm = (int) ($params['odometer_km'] ?? 0);

            $car = Car::findOrFail($carId);
            $user = User::findOrFail($userId);

            $existing = $user->vehicles()->where('plate_number', $plateNumber)->first();
            if ($existing !== null) {
                $existing->update([
                    'car_id' => $car->id,
                    'color' => $color,
                    'odometer_km' => $odometerKm,
                ]);
                $vehicle = $existing;
            } else {
                $vehicle = $this->acquiresVehicle->handle(
                    user: $user,
                    car: $car,
                    plateNumber: $plateNumber,
                    color: $color,
                    odometerKm: $odometerKm,
                    acquiredViaType: 'manual'
                );
            }

            // Remove from wishlist if present in AutoDex
            DB::table('dex_wishlists')
                ->where('user_id', $userId)
                ->where('car_id', $carId)
                ->delete();

            $this->audit(
                action: 'autodex_garage_inventory_synced',
                newValues: ['user_id' => $userId, 'car_id' => $carId, 'plate_number' => $plateNumber],
                impactType: 'customer_garage',
            );

            return [
                'id' => $vehicle->id,
                'user_id' => $userId,
                'car_id' => $carId,
                'plate_number' => $plateNumber,
                'color' => $color,
                'status' => 'active',
            ];
        });
    }
}
