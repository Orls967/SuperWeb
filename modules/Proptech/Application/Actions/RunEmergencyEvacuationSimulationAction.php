<?php

declare(strict_types=1);

namespace Modules\Proptech\Application\Actions;

use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Shared\Application\BaseAction;

class RunEmergencyEvacuationSimulationAction extends BaseAction
{
    /**
     * Run computational evacuation simulation on Digital Twin building spatial model.
     *
     * @return array{zone_id: int, hazard_type: string, initial_occupancy: int, estimated_clearance_sec: int, bottleneck_count: int, safety_rating: string, evacuation_status: string}
     */
    public function handle(
        int $zoneId,
        string $hazardType,
        int $initialOccupancy
    ): array {
        $zone = BuildingZone::findOrFail($zoneId);

        // Algorithmic flow-rate clearance calculation:
        // Standard egress flow: ~1.3 persons / meter-width / second
        // Assumed emergency exits: 2 with 1.2m doors -> effective throughput ~3.1 persons/sec
        $exitThroughputPerSec = 3.1;
        $stairwellDelaySec = match ($zone->floor_level) {
            'L1', 'GF' => 15,
            'L2', 'L3' => 35,
            default => 60,
        };

        $hazardMultiplier = match ($hazardType) {
            'FIRE' => 1.35,
            'GAS_LEAK' => 1.20,
            'EARTHQUAKE' => 1.50,
            default => 1.0,
        };

        $baseFlowSec = (int) ceil(($initialOccupancy / $exitThroughputPerSec) * $hazardMultiplier);
        $estimatedClearanceSec = $baseFlowSec + $stairwellDelaySec;

        $bottleneckCount = $initialOccupancy > 60 ? (int) ceil(($initialOccupancy - 60) / 25) : 0;

        [$safetyRating, $evacuationStatus] = match (true) {
            $estimatedClearanceSec <= 120 => ['GRADE_A_EXCELLENT', 'SAFE_EGRESS'],
            $estimatedClearanceSec <= 180 => ['GRADE_B_COMPLIANT', 'ACCEPTABLE_EGRESS'],
            $estimatedClearanceSec <= 300 => ['GRADE_C_MARGINAL', 'ELEVATED_RISK_BOTTLENECK'],
            default => ['GRADE_F_NON_COMPLIANT', 'CRITICAL_HAZARD_TRAP'],
        };

        $this->audit(
            action: 'proptech.evacuation.simulated',
            auditable: $zone,
            oldValues: null,
            newValues: [
                'zone_id' => $zone->id,
                'hazard_type' => $hazardType,
                'initial_occupancy' => $initialOccupancy,
                'estimated_clearance_sec' => $estimatedClearanceSec,
                'bottleneck_count' => $bottleneckCount,
                'safety_rating' => $safetyRating,
            ],
            correlationId: "prp_evac_{$zone->id}_".date('YmdHis'),
            impactType: 'life_safety_simulation'
        );

        return [
            'zone_id' => $zone->id,
            'hazard_type' => $hazardType,
            'initial_occupancy' => $initialOccupancy,
            'estimated_clearance_sec' => $estimatedClearanceSec,
            'bottleneck_count' => $bottleneckCount,
            'safety_rating' => $safetyRating,
            'evacuation_status' => $evacuationStatus,
        ];
    }
}
