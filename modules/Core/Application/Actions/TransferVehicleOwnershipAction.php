<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\TransfersVehicleOwnership;
use Modules\Core\Domain\Enums\VehicleEventType;
use Modules\Core\Domain\Events\VehicleOwnershipTransferred;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Shared\Application\BaseAction;

class TransferVehicleOwnershipAction extends BaseAction implements TransfersVehicleOwnership
{
    public function __construct(
        private readonly RecordVehicleEventAction $recordVehicleEvent,
    ) {}

    public function handle(
        object|int $vehicle,
        int $toUserId,
        string $viaType = 'manual',
        ?int $viaId = null,
        ?int $priceIdr = null,
        ?int $actorId = null,
    ): object {
        $vehicleId = $vehicle instanceof Vehicle ? $vehicle->id : (is_object($vehicle) ? $vehicle->id : $vehicle);

        return $this->transaction(function () use ($vehicleId, $toUserId, $viaType, $viaId, $priceIdr, $actorId) {
            /** @var Vehicle|null $locked */
            $locked = Vehicle::where('id', $vehicleId)->lockForUpdate()->first();

            if ($locked === null) {
                throw new InvalidArgumentException("Kendaraan #{$vehicleId} tidak ditemukan.");
            }

            $fromUserId = (int) $locked->user_id;

            if ($fromUserId === $toUserId) {
                throw new InvalidArgumentException('Kendaraan sudah dimiliki oleh pengguna tujuan.');
            }

            $locked->update([
                'user_id' => $toUserId,
                'acquired_at' => now(),
                'acquired_via_type' => $viaType,
                'acquired_via_id' => $viaId,
                'status' => 'active',
            ]);

            // Mobil yang sudah dimiliki tidak perlu lagi ada di wishlist pemilik baru
            DB::table('dex_wishlists')
                ->where('user_id', $toUserId)
                ->where('car_id', $locked->car_id)
                ->delete();

            $this->recordVehicleEvent->execute(
                vehicle: $locked,
                type: VehicleEventType::OWNERSHIP_TRANSFERRED,
                payload: [
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'via_type' => $viaType,
                    'via_id' => $viaId,
                    'price_idr' => $priceIdr,
                    'plate_number' => $locked->plate_number,
                    'odometer_km' => (int) $locked->odometer_km,
                ],
                actorId: $actorId ?? $toUserId,
            );

            event(new VehicleOwnershipTransferred(
                vehicle: $locked,
                fromUserId: $fromUserId,
                toUserId: $toUserId
            ));

            return $locked->fresh();
        });
    }
}
