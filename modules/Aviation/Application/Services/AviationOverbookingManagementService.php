<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationYieldBucket;

class AviationOverbookingManagementService
{
    /**
     * Hitung kapasitas overbooking optimal berbasis model no-show binomial diskret.
     *
     * @param  int  $capacity  Kapasitas fisik kabin pesawat
     * @param  float  $noShowProbability  Probabilitas no-show historis (e.g. 0.05 = 5%)
     * @param  int  $ticketMarginMinorUnit  Keuntungan marjinal tiket tambahan
     * @param  int  $deniedBoardingCostMinorUnit  Biaya penolakan terbang per penumpang
     * @param  float  $maxOverbookingRatio  Batas maksimum overbooking (e.g. 1.08 = 108%)
     * @return array{
     *     physical_capacity: int,
     *     optimal_booking_limit: int,
     *     overbooking_seats: int,
     *     spill_risk_probability: float,
     *     expected_denied_boarding_cost_idr: int,
     *     expected_net_gain_idr: int
     * }
     */
    public function calculateOptimalOverbooking(
        int $capacity,
        float $noShowProbability = 0.05,
        int $ticketMarginMinorUnit = 120000000,
        int $deniedBoardingCostMinorUnit = 150000000,
        float $maxOverbookingRatio = 1.08
    ): array {
        $pShow = 1.0 - $noShowProbability;
        $maxSeats = (int) floor($capacity * $maxOverbookingRatio);

        // Ambang kritis rasio Newsvendor: P(Show > C) <= Margin / (Margin + DeniedCost)
        $criticalRatio = $ticketMarginMinorUnit / (float) ($ticketMarginMinorUnit + $deniedBoardingCostMinorUnit);

        $bestSeats = $capacity;
        $bestGain = 0;
        $riskProb = 0.0;

        for ($b = $capacity; $b <= $maxSeats; $b++) {
            // Hitung probabilitas kehadiran melebihi kapasitas menggunakan distribusi binomial
            $probSpill = 0.0;
            $expectedDeniedPax = 0.0;

            for ($k = $capacity + 1; $k <= $b; $k++) {
                $probK = $this->binomialProbability($b, $k, $pShow);
                $probSpill += $probK;
                $expectedDeniedPax += ($k - $capacity) * $probK;
            }

            $revenueGain = ($b - $capacity) * $ticketMarginMinorUnit;
            $expectedLoss = (int) round($expectedDeniedPax * $deniedBoardingCostMinorUnit);
            $netGain = $revenueGain - $expectedLoss;

            if ($netGain >= $bestGain && $probSpill <= 0.05) { // Batasi risiko denied boarding maksimal 5%
                $bestGain = $netGain;
                $bestSeats = $b;
                $riskProb = $probSpill;
            }
        }

        $overbookingSeats = $bestSeats - $capacity;
        $expectedDeniedCost = (int) round(($riskProb * $overbookingSeats * $deniedBoardingCostMinorUnit) / 100);
        $expectedNetGain = (int) round($bestGain / 100);

        return [
            'physical_capacity' => $capacity,
            'optimal_booking_limit' => $bestSeats,
            'overbooking_seats' => $overbookingSeats,
            'spill_risk_probability' => round($riskProb, 4),
            'expected_denied_boarding_cost_idr' => $expectedDeniedCost,
            'expected_net_gain_idr' => $expectedNetGain,
        ];
    }

    /**
     * Terapkan batas overbooking yang dihitung pada penerbangan fisik.
     */
    public function applyOverbookingToFlight(int $flightId, float $noShowRate = 0.05): array
    {
        return DB::transaction(function () use ($flightId, $noShowRate) {
            $flight = AviationFlight::lockForUpdate()->findOrFail($flightId);
            $capacity = (int) $flight->capacity_seats;

            $result = $this->calculateOptimalOverbooking($capacity, $noShowRate);

            // Perbarui kapasitas alokasi pada bucket tarif ekonomi tertinggi (Y)
            $topBucket = AviationYieldBucket::where('flight_id', $flight->id)
                ->where('bucket_code', 'Y')
                ->first();

            if ($topBucket !== null) {
                $topBucket->allocated_capacity = $result['optimal_booking_limit'];
                $topBucket->booking_limit = $result['optimal_booking_limit'];
                $topBucket->save();
            }

            return $result;
        });
    }

    /**
     * Hitung fungsi massa probabilitas binomial: C(n, k) * p^k * (1-p)^(n-k).
     */
    protected function binomialProbability(int $n, int $k, float $p): float
    {
        if ($k < 0 || $k > $n) {
            return 0.0;
        }

        $logComb = 0.0;
        for ($i = 1; $i <= $k; $i++) {
            $logComb += log($n - $i + 1) - log($i);
        }

        $logProb = $logComb + ($k * log($p)) + (($n - $k) * log(1.0 - $p));

        return exp($logProb);
    }
}
