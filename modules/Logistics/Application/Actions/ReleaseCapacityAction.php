<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Models\CapacityReservation;
use Modules\Logistics\Domain\Models\Schedule;

class ReleaseCapacityAction
{
    /**
     * Atomically release reserved capacity and restore schedule counters.
     */
    public function execute(int|string|CapacityReservation $reservation): CapacityReservation
    {
        return DB::transaction(function () use ($reservation) {
            $query = CapacityReservation::query()->lockForUpdate();

            if ($reservation instanceof CapacityReservation) {
                $target = $query->findOrFail($reservation->id);
            } elseif (is_int($reservation)) {
                $target = $query->findOrFail($reservation);
            } else {
                $target = $query->where('idempotency_key', $reservation)->firstOrFail();
            }

            // Idempotent: If already released, return immediately
            if ($target->isReleased()) {
                return $target;
            }

            $schedule = Schedule::where('id', $target->schedule_id)->lockForUpdate()->firstOrFail();

            // Restore capacity
            $allocatedWeight = BigDecimal::of((string) ($target->allocated_weight_kg ?: '0.000'));
            $currentUsed = BigDecimal::of((string) ($schedule->used_weight_kg ?: '0.000'));
            $newUsedWeight = $currentUsed->minus($allocatedWeight);

            $schedule->used_weight_kg = (string) ($newUsedWeight->isNegative() ? '0.000' : $newUsedWeight);
            $schedule->used_volume_dm3 = max(0, ((int) $schedule->used_volume_dm3) - ((int) $target->allocated_volume_dm3));
            $schedule->used_teu = max(0, ((int) $schedule->used_teu) - ((int) $target->allocated_teu));
            $schedule->used_uld_positions = max(0, ((int) $schedule->used_uld_positions) - ((int) $target->allocated_uld_positions));
            $schedule->save();

            $target->status = 'released';
            $target->save();

            return $target;
        });
    }
}
