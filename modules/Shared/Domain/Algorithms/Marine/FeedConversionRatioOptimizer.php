<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Marine;

use DomainException;

/**
 * A16: FeedConversionRatioOptimizer.
 *
 * Mengoptimalkan Feed Conversion Ratio (FCR), mengevaluasi efisiensi pakan tambak udang / KJA ikan,
 * serta menghitung penyesuaian laju pemberian pakan harian (feeding rate adjustment)
 * berbasis telemetri kualitas air real-time (DO, Suhu, Salinitas, pH).
 */
class FeedConversionRatioOptimizer
{
    public const OPTIMAL_DO_MIN = 4.0; // mg/L minimum untuk nafsu makan optimal

    public const CRITICAL_DO_MIN = 2.5; // mg/L titik kritis pemotongan pakan 50%

    public const LETHAL_DO_MIN = 1.5;   // mg/L henti pakan darurat (0%)

    public const OPTIMAL_TEMP_MIN = 28.0; // °C

    public const OPTIMAL_TEMP_MAX = 31.5; // °C

    /**
     * Hitung Economic FCR dan Biological FCR.
     *
     * @return array{
     *     economic_fcr: float,
     *     biological_fcr: float,
     *     efficiency_rating: string,
     *     feed_loss_kg: float,
     *     biomass_gain_kg: float
     * }
     */
    public function calculateFcr(
        float $totalFeedUsedKg,
        float $initialBiomassKg,
        float $finalHarvestBiomassKg,
        float $mortalityBiomassKg = 0.0
    ): array {
        if ($totalFeedUsedKg < 0.0 || $initialBiomassKg < 0.0 || $finalHarvestBiomassKg < 0.0) {
            throw new DomainException('Parameter biomassa dan pakan tidak boleh negatif.');
        }

        $biomassGain = $finalHarvestBiomassKg - $initialBiomassKg;
        if ($biomassGain <= 0.0) {
            throw new DomainException('Biomassa panen akhir harus lebih besar dari biomassa awal tebar.');
        }

        $economicFcr = round($totalFeedUsedKg / $biomassGain, 3);

        $totalBiologicalGain = ($finalHarvestBiomassKg + $mortalityBiomassKg) - $initialBiomassKg;
        $biologicalFcr = $totalBiologicalGain > 0.0
            ? round($totalFeedUsedKg / $totalBiologicalGain, 3)
            : $economicFcr;

        $efficiency = 'EXCELLENT';
        if ($economicFcr > 1.8) {
            $efficiency = 'CRITICAL_HIGH';
        } elseif ($economicFcr > 1.5) {
            $efficiency = 'SUBOPTIMAL';
        } elseif ($economicFcr > 1.3) {
            $efficiency = 'GOOD';
        }

        $theoreticalOptimalFeed = $biomassGain * 1.25; // Benchmark target FCR 1.25
        $feedLoss = max(0.0, round($totalFeedUsedKg - $theoreticalOptimalFeed, 2));

        return [
            'economic_fcr' => $economicFcr,
            'biological_fcr' => $biologicalFcr,
            'efficiency_rating' => $efficiency,
            'feed_loss_kg' => $feedLoss,
            'biomass_gain_kg' => round($biomassGain, 2),
        ];
    }

    /**
     * Hitung penyesuaian dosis pakan harian berdasarkan telemetri sensor air.
     *
     * @return array{
     *     adjusted_daily_feed_kg: float,
     *     adjustment_multiplier: float,
     *     limitation_factors: array<string>,
     *     feeding_recommendation: string
     * }
     */
    public function calculateDailyRationAdjustment(
        float $baseRationKg,
        float $dissolvedOxygenMgL,
        float $waterTemperatureCelsius,
        float $phLevel,
        float $trayConsumptionRate = 1.0 // 0.0 - 1.0 (sisa anco)
    ): array {
        if ($baseRationKg <= 0.0) {
            throw new DomainException('Jatah pakan dasar harus lebih besar dari 0 kg.');
        }

        $multiplier = 1.0;
        $factors = [];

        // 1. Evaluasi Dissolved Oxygen (DO)
        if ($dissolvedOxygenMgL < self::LETHAL_DO_MIN) {
            $multiplier = 0.0;
            $factors[] = 'DO_CRITICAL_STOP_FEEDING';
        } elseif ($dissolvedOxygenMgL < self::CRITICAL_DO_MIN) {
            $multiplier *= 0.50;
            $factors[] = 'DO_LOW_CUT_50_PERCENT';
        } elseif ($dissolvedOxygenMgL < self::OPTIMAL_DO_MIN) {
            $multiplier *= 0.80;
            $factors[] = 'DO_SUBOPTIMAL_CUT_20_PERCENT';
        }

        // 2. Evaluasi Suhu Air
        if ($waterTemperatureCelsius < 26.0) {
            $multiplier *= 0.70;
            $factors[] = 'WATER_TEMP_COLD_METABOLISM_SLOW';
        } elseif ($waterTemperatureCelsius > 33.0) {
            $multiplier *= 0.75;
            $factors[] = 'WATER_TEMP_EXCESSIVE_STRESS';
        }

        // 3. Evaluasi pH
        if ($phLevel < 7.2 || $phLevel > 8.8) {
            $multiplier *= 0.85;
            $factors[] = 'PH_OUTSIDE_OPTIMAL_BAND';
        }

        // 4. Evaluasi Anco (Feeding Tray Leftover)
        if ($trayConsumptionRate < 0.60) {
            // Lebih dari 40% sisa pakan di anco
            $multiplier *= 0.60;
            $factors[] = 'HIGH_TRAY_LEFTOVER_REDUCE_FEED';
        } elseif ($trayConsumptionRate >= 0.98 && $dissolvedOxygenMgL >= self::OPTIMAL_DO_MIN) {
            // Habis bersih dan DO prima
            $multiplier *= 1.05;
            $factors[] = 'CLEAN_TRAY_OPTIMAL_BOOST_5_PERCENT';
        }

        $multiplier = max(0.0, min(1.20, round($multiplier, 3)));
        $adjustedDailyFeedKg = round($baseRationKg * $multiplier, 2);

        $recommendation = $multiplier === 0.0
            ? 'STOP_FEEDING_IMMEDIATELY_ACTIVATE_AERATORS'
            : ($multiplier < 0.8 ? 'REDUCE_RATION_MONITOR_WATER' : 'NORMAL_FEEDING_SCHEDULE');

        return [
            'adjusted_daily_feed_kg' => $adjustedDailyFeedKg,
            'adjustment_multiplier' => $multiplier,
            'limitation_factors' => $factors,
            'feeding_recommendation' => $recommendation,
        ];
    }
}
