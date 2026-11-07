<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Modules\Mining\Domain\Models\MiningPlanShift;

/**
 * Kurva-S produksi rencana vs aktual per pit (D93-1), seluruhnya dari fakta min_mine_plan_shifts.
 * SPI dalam bps = kumulatif aktual × 10.000 / kumulatif rencana (bulat ke bawah).
 */
final class PitProductionSCurveService
{
    /**
     * @return array{
     *     points: list<array{date: string, planned_kg: int, actual_kg: int, planned_cum_kg: int, actual_cum_kg: int, variance_kg: int}>,
     *     spi_bps: int,
     *     status: string
     * }
     */
    public function build(string $pitId, string $month): array
    {
        $daily = MiningPlanShift::query()
            ->where('pit_id', $pitId)
            ->whereDate('shift_date', '>=', "{$month}-01")
            ->whereDate('shift_date', '<=', "{$month}-31")
            ->orderBy('shift_date')
            ->get()
            ->groupBy(fn (MiningPlanShift $s) => $s->shift_date->format('Y-m-d'));

        $points = [];
        $plannedCum = 0;
        $actualCum = 0;

        foreach ($daily as $date => $shifts) {
            $planned = (int) $shifts->sum('target_tonnage_kg');
            $actual = (int) $shifts->sum('actual_tonnage_kg');
            $plannedCum += $planned;
            $actualCum += $actual;

            $points[] = [
                'date' => (string) $date,
                'planned_kg' => $planned,
                'actual_kg' => $actual,
                'planned_cum_kg' => $plannedCum,
                'actual_cum_kg' => $actualCum,
                'variance_kg' => $actualCum - $plannedCum,
            ];
        }

        $spiBps = $plannedCum > 0 ? intdiv($actualCum * 10000, $plannedCum) : 0;

        return [
            'points' => $points,
            'spi_bps' => $spiBps,
            'status' => $spiBps >= 9500 ? 'ON_TRACK' : ($spiBps >= 8500 ? 'BEHIND' : 'CRITICAL'),
        ];
    }
}
