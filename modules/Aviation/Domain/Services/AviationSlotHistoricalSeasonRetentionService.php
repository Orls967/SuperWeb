<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Services;

use Modules\Aviation\Domain\Models\AviationSlot;

class AviationSlotHistoricalSeasonRetentionService
{
    /**
     * Evaluasi seluruh portofolio slot maskapai untuk menentukan kelayakan hak historis musim berikutnya.
     *
     * @return array{
     *     airline_id: int,
     *     season: string,
     *     total_slots_held: int,
     *     slots_retained_count: int,
     *     slots_at_risk_count: int,
     *     overall_portfolio_retention_rate: float
     * }
     */
    public function evaluateAirlinePortfolio(int $airlineId, string $seasonCode): array
    {
        $slots = AviationSlot::where('airline_id', $airlineId)->get();

        if ($slots->isEmpty()) {
            return [
                'airline_id' => $airlineId,
                'season' => $seasonCode,
                'total_slots_held' => 0,
                'slots_retained_count' => 0,
                'slots_at_risk_count' => 0,
                'overall_portfolio_retention_rate' => 100.0,
            ];
        }

        $retained = 0;
        $atRisk = 0;

        foreach ($slots as $s) {
            // Evaluasi status alokasi dan rasio utilisasi
            $ratio = $s->utilization_ratio ?? 0.85;
            if ($ratio >= 0.80) {
                $retained++;
            } else {
                $atRisk++;
            }
        }

        $rate = ($retained / (float) $slots->count()) * 100.0;

        return [
            'airline_id' => $airlineId,
            'season' => $seasonCode,
            'total_slots_held' => $slots->count(),
            'slots_retained_count' => $retained,
            'slots_at_risk_count' => $atRisk,
            'overall_portfolio_retention_rate' => round($rate, 2),
        ];
    }
}
