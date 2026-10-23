<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningDispatchRun;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
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

    /**
     * Two-source mining operations audit.
     * Cross-checks equipment marked 'dispatched' vs dispatch runs marked 'in_progress'.
     */
    public function auditMining(): array
    {
        $siteCount = MiningSite::count();
        $pitCount = MiningPit::count();
        $equipmentCount = MiningEquipment::count();
        $dispatchCount = MiningDispatchRun::count();
        $discrepancies = 0;

        // Two-source: equipment 'dispatched' must have an in_progress dispatch run
        $dispatchedEquipment = MiningEquipment::where('status', 'dispatched')->get();
        foreach ($dispatchedEquipment as $equip) {
            $hasRun = MiningDispatchRun::where('equipment_id', $equip->id)
                ->where('status', 'in_progress')
                ->exists();
            if (! $hasRun) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'site_count' => $siteCount,
            'pit_count' => $pitCount,
            'equipment_count' => $equipmentCount,
            'dispatch_count' => $dispatchCount,
            'discrepancies' => $discrepancies,
        ];
    }

    /**
     * Predictive maintenance trigger based on engine hours threshold.
     * If engine hours exceed threshold and equipment is not already in maintenance, trigger work order once.
     */
    public function recordEngineHoursAndCheckMaintenance(string $equipmentId, int $additionalHours, int $thresholdHours = 1500): array
    {
        $equipment = MiningEquipment::findOrFail($equipmentId);
        $equipment->increment('engine_hours', $additionalHours);
        $currentHours = (int) $equipment->fresh()->engine_hours;

        $triggered = false;
        if ($currentHours >= $thresholdHours && $equipment->status !== 'maintenance') {
            $equipment->update(['status' => 'maintenance']);
            $triggered = true;
        }

        return [
            'equipment_id' => $equipment->id,
            'equipment_code' => $equipment->equipment_code,
            'engine_hours' => $currentHours,
            'maintenance_triggered' => $triggered,
            'status' => $equipment->fresh()->status,
        ];
    }

    /**
     * Dispatch board summary within strict query budget (<= 2 queries).
     */
    public function getDispatchBoardSummary(string $siteId): array
    {
        $equipmentCounts = MiningEquipment::where('site_id', $siteId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $activeRunsCount = MiningDispatchRun::where('site_id', $siteId)
            ->where('status', 'in_progress')
            ->count();

        return [
            'site_id' => $siteId,
            'available_equipment' => $equipmentCounts['available'] ?? 0,
            'dispatched_equipment' => $equipmentCounts['dispatched'] ?? 0,
            'maintenance_equipment' => $equipmentCounts['maintenance'] ?? 0,
            'active_runs' => $activeRunsCount,
        ];
    }
}
