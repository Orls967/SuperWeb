<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelBanquetFloorSafetyAuditor
{
    /**
     * Memeriksa kepatuhan keselamatan darurat (Fire Safety & Emergency Egress) pada denah layout acara jamuan.
     *
     * @return array{contract_id: string, emergency_exit_clearance_meters: float, aisle_width_meters: float, is_fire_egress_safe: bool, max_permissible_occupancy: int, safety_clearance_status: string}
     */
    public function auditHallSafetyCompliance(
        string $contractId,
        float $narrowestAisleMeters,
        int $emergencyExitsCount,
        int $plannedOccupancy
    ): array {
        // Standar keselamatan NFPA / PMK: lebar lorong evakuasi minimal 1.4 meter, 1 pintu darurat per 250 orang
        $isAisleSafe = $narrowestAisleMeters >= 1.4;
        $maxSafeOccupancy = $emergencyExitsCount * 250;
        $isCapacitySafe = $plannedOccupancy <= $maxSafeOccupancy;

        $isCompliant = $isAisleSafe && $isCapacitySafe;
        $status = $isCompliant ? 'SAFETY_CERTIFIED' : 'EGRESS_VIOLATION_REVISE_LAYOUT';

        return [
            'contract_id' => $contractId,
            'emergency_exit_clearance_meters' => $narrowestAisleMeters,
            'aisle_width_meters' => $narrowestAisleMeters,
            'is_fire_egress_safe' => $isCompliant,
            'max_permissible_occupancy' => $maxSafeOccupancy,
            'safety_clearance_status' => $status,
        ];
    }
}
