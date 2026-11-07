<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

final class BanquetFloorPlanOptimizationEngine
{
    /**
     * Mengoptimasi tata letak meja bundar dan alokasi lorong gerak pramusaji pada ruang banquet.
     *
     * @return array{
     *     hall_area_sqm: float,
     *     target_pax: int,
     *     round_tables_needed: int,
     *     recommended_table_diameter_meters: float,
     *     service_aisle_width_meters: float,
     *     density_category: string,
     *     is_layout_compliant: bool
     * }
     */
    public function optimizeHallLayout(
        float $hallAreaSquareMeters,
        int $targetPax,
        bool $isVipHighComfort = false
    ): array {
        if ($hallAreaSquareMeters <= 0 || $targetPax <= 0) {
            throw new InvalidArgumentException('Luas aula dan jumlah tamu harus bernilai positif.');
        }

        // Standar ruang per tamu: VIP 2.2 m2 per pax, Reguler 1.6 m2 per pax
        $spacePerPax = $isVipHighComfort ? 2.2 : 1.6;
        $maxCapacity = (int) floor($hallAreaSquareMeters / $spacePerPax);

        // Kapasitas 1 meja bundar = 10 pax
        $tablesNeeded = (int) ceil($targetPax / 10);
        $tableDiameter = $isVipHighComfort ? 1.8 : 1.6; // Meja 180cm vs 160cm
        $aisleWidth = $isVipHighComfort ? 2.0 : 1.5; // Lebar lorong servis 2 meter vs 1.5 meter

        $density = 'COMFORTABLE';
        if ($targetPax > $maxCapacity) {
            $density = 'OVERCROWDED_WARNING';
        } elseif ($targetPax < ($maxCapacity * 0.5)) {
            $density = 'SPACIOUS';
        }

        return [
            'hall_area_sqm' => $hallAreaSquareMeters,
            'target_pax' => $targetPax,
            'round_tables_needed' => $tablesNeeded,
            'recommended_table_diameter_meters' => $tableDiameter,
            'service_aisle_width_meters' => $aisleWidth,
            'density_category' => $density,
            'is_layout_compliant' => $targetPax <= $maxCapacity,
        ];
    }
}
