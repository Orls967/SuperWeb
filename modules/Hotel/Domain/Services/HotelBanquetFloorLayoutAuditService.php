<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelBanquetFloorLayoutAuditService
{
    /**
     * Memvalidasi rasio penempatan panggung, sound control console, dan area buffet terhadap kapasitas ruangan.
     *
     * @return array{contract_id: string, stage_area_sqm: float, buffet_stations_count: int, sound_console_sqm: float, net_usable_guest_area_sqm: float, layout_clearance_status: string}
     */
    public function auditAuxiliarySpaces(
        string $contractId,
        float $hallSquareMeters,
        float $stageWidthMeters = 12.0,
        float $stageDepthMeters = 4.0,
        int $buffetStations = 3
    ): array {
        $stageArea = $stageWidthMeters * $stageDepthMeters; // 48 m2
        $buffetArea = $buffetStations * 15.0; // 45 m2
        $soundConsoleArea = 12.0; // 12 m2

        $totalAuxiliary = $stageArea + $buffetArea + $soundConsoleArea;
        $netUsable = max(0.0, $hallSquareMeters - $totalAuxiliary);

        $status = ($netUsable >= ($hallSquareMeters * 0.60)) ? 'AUXILIARY_BALANCED' : 'EXCESSIVE_STAGING_FOOTPRINT';

        return [
            'contract_id' => $contractId,
            'stage_area_sqm' => $stageArea,
            'buffet_stations_count' => $buffetStations,
            'sound_console_sqm' => $soundConsoleArea,
            'net_usable_guest_area_sqm' => round($netUsable, 1),
            'layout_clearance_status' => $status,
        ];
    }
}
