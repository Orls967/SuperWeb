<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningDispatchRun;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Shared\Application\BaseAction;

class DispatchHaulTruckAndDetectFuelAnomalyAction extends BaseAction
{
    /**
     * Dispatch haul truck trip, record payload variance, and detect specific fuel consumption (SFC) anomalies.
     *
     * @return array{dispatch_run: MiningDispatchRun, payload_variance_ton: float, fuel_anomaly_detected: bool, contractor_payment_held: bool}
     */
    public function handle(
        string $siteId,
        string $pitId,
        string $equipmentId,
        string $shiftCode,
        float $targetPayloadTon,
        float $actualPayloadTon,
        float $fuelConsumedLiter,
        float $distanceKm
    ): array {
        return DB::transaction(function () use (
            $siteId,
            $pitId,
            $equipmentId,
            $shiftCode,
            $targetPayloadTon,
            $actualPayloadTon,
            $fuelConsumedLiter,
            $distanceKm
        ) {
            $equipment = MiningEquipment::lockForUpdate()->findOrFail($equipmentId);

            $payloadVarianceTon = round($actualPayloadTon - $targetPayloadTon, 2);

            // Baseline SFC for haul truck: ~1.85 L / km loaded
            $expectedFuelLiter = max(5.0, round($distanceKm * 1.85, 2));
            $fuelVariancePct = $expectedFuelLiter > 0
                ? round((($fuelConsumedLiter - $expectedFuelLiter) / $expectedFuelLiter) * 100, 2)
                : 0.0;

            // Fuel anomaly flagged if consumption exceeds expected by > 12.0%
            $fuelAnomalyDetected = ($fuelVariancePct > 12.0);
            $contractorPaymentHeld = $fuelAnomalyDetected;

            $dispatchRun = MiningDispatchRun::create([
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'pit_id' => $pitId,
                'equipment_id' => $equipment->id,
                'shift_code' => $shiftCode,
                'target_payload_ton' => $targetPayloadTon,
                'actual_payload_ton' => $actualPayloadTon,
                'payload_variance_ton' => $payloadVarianceTon,
                'fuel_consumed_liter' => $fuelConsumedLiter,
                'distance_km' => $distanceKm,
                'fuel_anomaly_detected' => $fuelAnomalyDetected,
                'contractor_payment_held' => $contractorPaymentHeld,
                'status' => 'completed',
            ]);

            $this->audit(
                action: 'mining.dispatch.completed',
                auditable: $dispatchRun,
                oldValues: null,
                newValues: [
                    'equipment_code' => $equipment->equipment_code,
                    'actual_payload_ton' => $actualPayloadTon,
                    'fuel_consumed_liter' => $fuelConsumedLiter,
                    'fuel_anomaly_detected' => $fuelAnomalyDetected,
                    'contractor_payment_held' => $contractorPaymentHeld,
                ],
                correlationId: "min_disp_{$dispatchRun->id}",
                impactType: 'fleet_dispatch_telematics'
            );

            return [
                'dispatch_run' => $dispatchRun,
                'payload_variance_ton' => $payloadVarianceTon,
                'fuel_anomaly_detected' => $fuelAnomalyDetected,
                'contractor_payment_held' => $contractorPaymentHeld,
            ];
        });
    }
}
