<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningDispatchRun;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use RuntimeException;

class MiningFleetDispatchService
{
    /**
     * Dispatch haul truck with target payload
     */
    public function dispatchEquipment(
        string $siteId,
        string $pitId,
        string $equipmentId,
        string $shiftCode,
        float $targetPayloadTon
    ): MiningDispatchRun {
        $equipment = MiningEquipment::findOrFail($equipmentId);
        if ($equipment->status !== 'available') {
            throw new RuntimeException("Equipment {$equipment->equipment_code} is not available for dispatch (status: {$equipment->status})");
        }

        $equipment->update(['status' => 'dispatched']);

        return MiningDispatchRun::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteId,
            'pit_id' => $pitId,
            'equipment_id' => $equipment->id,
            'shift_code' => $shiftCode,
            'target_payload_ton' => $targetPayloadTon,
            'actual_payload_ton' => 0.00,
            'payload_variance_ton' => 0.00,
            'fuel_consumed_liter' => 0.00,
            'distance_km' => 0.00,
            'fuel_anomaly_detected' => false,
            'contractor_payment_held' => false,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Complete dispatch run, record actual payload & fuel telemetry, detect fuel theft anomaly
     */
    public function completeDispatchRun(
        string $runId,
        float $actualPayloadTon,
        float $fuelConsumedLiter,
        float $distanceKm
    ): MiningDispatchRun {
        $run = MiningDispatchRun::findOrFail($runId);
        $equipment = MiningEquipment::findOrFail($run->equipment_id);
        $pit = MiningPit::findOrFail($run->pit_id);

        $payloadVariance = $actualPayloadTon - (float) $run->target_payload_ton;

        // Baseline fuel consumption benchmark: e.g. 0.45 liter per ton-km
        $tonKm = $actualPayloadTon * $distanceKm;
        $expectedFuel = $tonKm > 0 ? $tonKm * 0.45 : 10.0;

        // Fuel anomaly detected if consumption is > 50% above expected
        $isFuelAnomaly = $fuelConsumedLiter > ($expectedFuel * 1.5);
        $paymentHeld = $isFuelAnomaly;

        $run->update([
            'actual_payload_ton' => $actualPayloadTon,
            'payload_variance_ton' => $payloadVariance,
            'fuel_consumed_liter' => $fuelConsumedLiter,
            'distance_km' => $distanceKm,
            'fuel_anomaly_detected' => $isFuelAnomaly,
            'contractor_payment_held' => $paymentHeld,
            'status' => 'completed',
        ]);

        $pit->increment('actual_production_ton', (int) round($actualPayloadTon));

        // Increment equipment engine hours based on distance / average speed
        $addedHours = max(1, (int) round($distanceKm / 20));
        $equipment->increment('engine_hours', $addedHours);
        $equipment->update(['status' => 'available']);

        return $run->fresh();
    }
}
