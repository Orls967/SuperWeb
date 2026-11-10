<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Models\BillingEpisode;

/**
 * Layanan peramalan tingkat okupansi dan perputaran tempat tidur (Bed Turnover Rate).
 */
final class BedOccupancyForecastService
{
    public const CAPACITY = 120;

    /**
     * @return array{
     *     total_capacity: int,
     *     current_occupied: int,
     *     projected_discharges_3d: int,
     *     forecasted_occupancy_pct: float,
     *     risk_level: string
     * }
     */
    public function forecastNext3Days(): array
    {
        $currentActive = BillingEpisode::where('status', 'active')->count();

        // Estimasi discharge dalam 3 hari ke depan (berdasarkan lama rawat)
        $projectedDischarges = (int) floor($currentActive * 0.35);
        $projectedAdmissions = 15; // Estimasi rata-rata intake

        $netProjected = max(0, $currentActive - $projectedDischarges + $projectedAdmissions);
        $occupancyPct = round(($netProjected / self::CAPACITY) * 100, 2);

        $riskLevel = match (true) {
            $occupancyPct >= 90.0 => 'CRITICAL_HIGH',
            $occupancyPct >= 75.0 => 'ELEVATED',
            $occupancyPct >= 50.0 => 'NORMAL',
            default => 'LOW',
        };

        return [
            'total_capacity' => self::CAPACITY,
            'current_occupied' => $currentActive,
            'projected_discharges_3d' => $projectedDischarges,
            'forecasted_occupancy_pct' => $occupancyPct,
            'risk_level' => $riskLevel,
        ];
    }
}
