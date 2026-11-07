<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Algorithms\BestAvailableRateCalculator;

/**
 * Layanan Optimasi Tarif Kamar Dinamis Berbasis Okupansi & Paritas Pasar (H2).
 */
final class HotelDynamicRateOptimizerService
{
    /**
     * @param array<string, mixed> $competitorRates
     * @return array{optimal_rate_idr: int, applied_multiplier_bps: int, parity_status: string, certificate: array<string, mixed>}
     */
    public function optimizeRoomTypeRate(
        int $baseRateIdr,
        float $currentOccupancyRate,
        int $leadDays,
        bool $isPeakSeason,
        ?int $contractRateIdr = null,
        array $competitorRates = []
    ): array {
        $barResult = BestAvailableRateCalculator::calculate(
            baseRateIdr: $baseRateIdr,
            occupancyRate: $currentOccupancyRate,
            leadDays: $leadDays,
            isEventActive: $isPeakSeason,
            contractRateIdr: $contractRateIdr
        );

        $optimalRate = $barResult['rate_idr'];
        $parityStatus = 'in_parity';

        if (!empty($competitorRates)) {
            $avgCompetitor = (int) (array_sum($competitorRates) / count($competitorRates));
            if ($optimalRate > (int) round($avgCompetitor * 1.20)) {
                $parityStatus = 'above_market_ceiling';
            } elseif ($optimalRate < (int) round($avgCompetitor * 0.85)) {
                $parityStatus = 'undercutting_market';
            }
        }

        return [
            'optimal_rate_idr' => $optimalRate,
            'applied_multiplier_bps' => $barResult['multiplier_bps'],
            'parity_status' => $parityStatus,
            'certificate' => $barResult['certificate'],
        ];
    }
}
