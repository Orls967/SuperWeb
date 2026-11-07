<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final class TimeshareExchangeFairnessIndexCalculator
{
    /**
     * @var array<string, float>
     */
    private const SEASON_WEIGHTS = [
        'PEAK' => 1.50,
        'SHOULDER' => 1.00,
        'REGULAR' => 0.75,
    ];

    /**
     * Menghitung indeks keadilan pertukaran minggu vila (Fairness Index 0.0 - 1.0) dan besaran balancing fee yang adil.
     *
     * @return array{
     *     source_season: string,
     *     target_season: string,
     *     fairness_index: float,
     *     is_fair_swap: bool,
     *     recommended_balancing_fee_idr: int
     * }
     */
    public function evaluateSwapFairness(
        string $sourceSeason,
        string $targetSeason,
        int $dailyRentalBenchmarkIdr = 7500000
    ): array {
        $sourceWeight = self::SEASON_WEIGHTS[$sourceSeason] ?? 1.00;
        $targetWeight = self::SEASON_WEIGHTS[$targetSeason] ?? 1.00;

        $ratio = round($sourceWeight / $targetWeight, 4);
        $fairnessIndex = min(1.0, $ratio);

        $balancingFee = 0;
        if ($sourceWeight < $targetWeight) {
            $deltaWeight = $targetWeight - $sourceWeight;
            // Balancing fee = Delta Weight * Benchmark Sewa 7 Hari * faktor subsidi 20%
            $balancingFee = (int) round($deltaWeight * ($dailyRentalBenchmarkIdr * 7) * 0.20);
        }

        return [
            'source_season' => $sourceSeason,
            'target_season' => $targetSeason,
            'fairness_index' => $fairnessIndex,
            'is_fair_swap' => $fairnessIndex >= 0.65,
            'recommended_balancing_fee_idr' => $balancingFee,
        ];
    }
}
