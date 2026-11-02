<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Application\Actions;

use Modules\Mobility\Fleet\Domain\Models\FleetContractUnit;
use Modules\Shared\Application\BaseAction;

class BillExcessMileageAndWearTearAction extends BaseAction
{
    /**
     * Bill excess mileage and wear & tear damage for a returned or audited fleet lease unit.
     *
     * @return array{unit: FleetContractUnit, excess_km: int, excess_mileage_fee_idr: int, wear_tear_fee_idr: int, total_billed_idr: int}
     */
    public function handle(
        int $contractUnitId,
        int $actualReturnOdometerKm,
        int $allowedKmCap,
        int $ratePerKmIdr = 2500,
        int $wearTearFeeIdr = 0,
        ?string $conditionNotes = null
    ): array {
        return $this->transaction(function () use (
            $contractUnitId,
            $actualReturnOdometerKm,
            $allowedKmCap,
            $ratePerKmIdr,
            $wearTearFeeIdr,
            $conditionNotes
        ) {
            $unit = FleetContractUnit::where('id', $contractUnitId)->lockForUpdate()->firstOrFail();

            $totalKmDriven = max(0, $actualReturnOdometerKm - $unit->baseline_odometer_km);
            $excessKm = max(0, $totalKmDriven - $allowedKmCap);
            $excessMileageFeeIdr = $excessKm * $ratePerKmIdr;
            $totalBilledIdr = $excessMileageFeeIdr + $wearTearFeeIdr;

            $unit->current_odometer_km = $actualReturnOdometerKm;
            if ($conditionNotes) {
                $unit->condition_report_notes = $conditionNotes;
            }
            $unit->save();

            $idempotencyKey = hash('sha256', "fleet:unit:excess:{$unit->id}:{$actualReturnOdometerKm}:{$totalBilledIdr}");

            $this->audit(
                action: 'fleet.unit.excess_mileage_billed',
                auditable: $unit,
                oldValues: null,
                newValues: [
                    'actual_odometer' => $actualReturnOdometerKm,
                    'excess_km' => $excessKm,
                    'excess_fee_idr' => $excessMileageFeeIdr,
                    'wear_tear_idr' => $wearTearFeeIdr,
                    'total_billed_idr' => $totalBilledIdr,
                ],
                correlationId: "excess_unit_{$unit->id}",
                impactType: 'financial_billing'
            );

            $this->outbox(
                eventType: 'FleetExcessMileageBilled',
                payload: [
                    'contract_unit_id' => $unit->id,
                    'contract_id' => $unit->contract_id,
                    'excess_km' => $excessKm,
                    'total_billed_idr' => $totalBilledIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'unit' => $unit,
                'excess_km' => $excessKm,
                'excess_mileage_fee_idr' => $excessMileageFeeIdr,
                'wear_tear_fee_idr' => $wearTearFeeIdr,
                'total_billed_idr' => $totalBilledIdr,
            ];
        });
    }
}
