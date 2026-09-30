<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Exceptions\CapacityCutoffExceededException;
use Modules\Logistics\Domain\Exceptions\CapacityExceededException;
use Modules\Logistics\Domain\Exceptions\ScheduleConflictException;
use Modules\Logistics\Domain\Models\CapacityReservation;
use Modules\Logistics\Domain\Models\Schedule;

class ReserveCapacityAction
{
    /**
     * Atomically reserve multi-dimensional capacity on an operation schedule with lockForUpdate.
     */
    public function execute(
        int $scheduleId,
        string|int|float|BigDecimal $weightKg,
        int $volumeDm3,
        int $teu = 0,
        int $uldPositions = 0,
        string $idempotencyKey = '',
        ?int $shipmentId = null
    ): CapacityReservation {
        return DB::transaction(function () use (
            $scheduleId,
            $weightKg,
            $volumeDm3,
            $teu,
            $uldPositions,
            $idempotencyKey,
            $shipmentId
        ) {
            // 1. Idempotency Check: if already reserved with this key, return without double allocating
            if (! empty($idempotencyKey)) {
                $existing = CapacityReservation::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            // 2. Lock schedule row exclusively
            $schedule = Schedule::where('id', $scheduleId)->lockForUpdate()->firstOrFail();

            // 3. Cut-off Validation
            if ($schedule->isPastCutoff()) {
                throw CapacityCutoffExceededException::forSchedule(
                    $schedule->schedule_number,
                    $schedule->cutoff_at->toDateTimeString()
                );
            }

            // 4. Status Validation
            if (in_array($schedule->status, [
                ScheduleStatus::Departed,
                ScheduleStatus::Arrived,
                ScheduleStatus::Completed,
                ScheduleStatus::Cancelled,
            ], true)) {
                throw new \InvalidArgumentException("Jadwal '{$schedule->schedule_number}' berstatus '{$schedule->status->value}' dan tidak menerima alokasi kargo.");
            }

            // 5. Driver / Asset Overlap Conflict Detection
            if ($schedule->driver_id) {
                $driverConflict = Schedule::where('driver_id', $schedule->driver_id)
                    ->where('id', '!=', $schedule->id)
                    ->whereNotIn('status', [ScheduleStatus::Completed->value, ScheduleStatus::Cancelled->value])
                    ->where(function ($q) use ($schedule) {
                        $q->where('etd', '<', $schedule->eta)
                            ->where('eta', '>', $schedule->etd);
                    })
                    ->first();

                if ($driverConflict) {
                    $driverNum = $schedule->driver?->driver_number ?? (string) $schedule->driver_id;
                    throw ScheduleConflictException::forDriver($driverNum, $driverConflict->schedule_number);
                }
            }

            if ($schedule->asset_type && $schedule->asset_id) {
                $assetConflict = Schedule::where('asset_type', $schedule->asset_type)
                    ->where('asset_id', $schedule->asset_id)
                    ->where('id', '!=', $schedule->id)
                    ->whereNotIn('status', [ScheduleStatus::Completed->value, ScheduleStatus::Cancelled->value])
                    ->where(function ($q) use ($schedule) {
                        $q->where('etd', '<', $schedule->eta)
                            ->where('eta', '>', $schedule->etd);
                    })
                    ->first();

                if ($assetConflict) {
                    $assetDesc = class_basename($schedule->asset_type)." #{$schedule->asset_id}";
                    throw ScheduleConflictException::forAsset($assetDesc, $assetConflict->schedule_number);
                }
            }

            // 6. Multi-dimensional Capacity Validation
            $neededWeight = $weightKg instanceof BigDecimal ? $weightKg : BigDecimal::of((string) ($weightKg ?: '0'));

            if (! $schedule->hasAvailableCapacity($neededWeight, $volumeDm3, $teu, $uldPositions)) {
                $reasons = [];
                if ($schedule->remainingWeightKg()->isLessThan($neededWeight)) {
                    $reasons[] = "Sisa berat {$schedule->remainingWeightKg()} kg < {$neededWeight} kg";
                }
                if ($volumeDm3 > 0 && $schedule->remainingVolumeDm3() < $volumeDm3) {
                    $reasons[] = "Sisa volume {$schedule->remainingVolumeDm3()} dm³ < {$volumeDm3} dm³";
                }
                if ($teu > 0 && $schedule->remainingTeu() < $teu) {
                    $reasons[] = "Sisa TEU {$schedule->remainingTeu()} < {$teu}";
                }
                if ($uldPositions > 0 && $schedule->remainingUldPositions() < $uldPositions) {
                    $reasons[] = "Sisa ULD {$schedule->remainingUldPositions()} < {$uldPositions}";
                }

                throw CapacityExceededException::forSchedule(
                    $schedule->schedule_number,
                    implode(', ', $reasons)
                );
            }

            // 7. Increment Used Counters
            $newUsedWeight = BigDecimal::of((string) ($schedule->used_weight_kg ?: '0'))->plus($neededWeight);
            $schedule->used_weight_kg = (string) $newUsedWeight;
            $schedule->used_volume_dm3 = ((int) $schedule->used_volume_dm3) + $volumeDm3;
            $schedule->used_teu = ((int) $schedule->used_teu) + $teu;
            $schedule->used_uld_positions = ((int) $schedule->used_uld_positions) + $uldPositions;
            $schedule->save();

            // 8. Persist Capacity Reservation
            return CapacityReservation::create([
                'schedule_id' => $schedule->id,
                'shipment_id' => $shipmentId,
                'idempotency_key' => $idempotencyKey ?: (string) Str::uuid(),
                'allocated_weight_kg' => (string) $neededWeight,
                'allocated_volume_dm3' => $volumeDm3,
                'allocated_teu' => $teu,
                'allocated_uld_positions' => $uldPositions,
                'status' => 'active',
            ]);
        });
    }
}
