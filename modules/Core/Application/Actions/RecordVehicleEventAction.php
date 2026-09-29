<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Enums\VehicleEventType;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Core\Domain\Models\VehicleEvent;

class RecordVehicleEventAction
{
    public function execute(
        Vehicle $vehicle,
        VehicleEventType $type,
        array $payload,
        ?int $actorId = null,
        ?CarbonInterface $occurredAt = null
    ): VehicleEvent {
        return DB::transaction(function () use ($vehicle, $type, $payload, $actorId, $occurredAt) {
            $latestEvent = VehicleEvent::where('vehicle_id', $vehicle->id)
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();

            if (! $latestEvent) {
                $sequence = 1;
                $prevHash = str_repeat('0', 64);
            } else {
                $sequence = $latestEvent->sequence + 1;
                $prevHash = $latestEvent->hash;
            }

            $occurredAt = $occurredAt ?? now();
            $hash = VehicleEvent::calculateHash($prevHash, $sequence, $type, $payload, $occurredAt);

            return VehicleEvent::create([
                'vehicle_id' => $vehicle->id,
                'sequence' => $sequence,
                'type' => $type,
                'payload' => $payload,
                'occurred_at' => $occurredAt,
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'actor_id' => $actorId,
            ]);
        });
    }
}
