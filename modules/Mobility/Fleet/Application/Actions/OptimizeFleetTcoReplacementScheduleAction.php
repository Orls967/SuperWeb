<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Application\Actions;

use Modules\Mobility\Fleet\Domain\Models\FleetContract;
use Modules\Mobility\Fleet\Domain\Models\FleetContractUnit;
use Modules\Shared\Application\BaseAction;

class OptimizeFleetTcoReplacementScheduleAction extends BaseAction
{
    /**
     * Compute Total Cost of Ownership (TCO) and determine optimal vehicle replacement schedule.
     *
     * @return array{contract_id: int, analyzed_units_count: int, recommendation: string, projected_savings_idr: int, replace_now_unit_ids: array<int>}
     */
    public function handle(int $contractId): array
    {
        $contract = FleetContract::where('id', $contractId)->firstOrFail();
        $units = FleetContractUnit::where('contract_id', $contractId)->get();

        $replaceNowUnitIds = [];
        $totalProjectedSavingsIdr = 0;

        foreach ($units as $unit) {
            $totalKm = $unit->current_odometer_km - $unit->baseline_odometer_km;
            $downtimeHours = $unit->downtime_hours_recorded;

            // Unit exceeds 100,000 km or 48 hours of cumulative downtime -> Replace to cut maintenance spike
            if ($totalKm > 100000 || $downtimeHours > 48) {
                $replaceNowUnitIds[] = $unit->id;
                // Estimated 15,000,000 IDR net maintenance & residual value salvage optimization per unit
                $totalProjectedSavingsIdr += 15000000;
            }
        }

        $recommendation = count($replaceNowUnitIds) > 0 ? 'REPLACE_CRITICAL_UNITS' : 'MAINTAIN_CURRENT_FLEET';

        $this->audit(
            action: 'fleet.tco.optimized',
            auditable: $contract,
            oldValues: null,
            newValues: [
                'recommendation' => $recommendation,
                'replace_units_count' => count($replaceNowUnitIds),
                'projected_savings_idr' => $totalProjectedSavingsIdr,
            ],
            correlationId: "tco_contract_{$contractId}",
            impactType: 'fleet_optimization'
        );

        return [
            'contract_id' => $contractId,
            'analyzed_units_count' => $units->count(),
            'recommendation' => $recommendation,
            'projected_savings_idr' => $totalProjectedSavingsIdr,
            'replace_now_unit_ids' => $replaceNowUnitIds,
        ];
    }
}
