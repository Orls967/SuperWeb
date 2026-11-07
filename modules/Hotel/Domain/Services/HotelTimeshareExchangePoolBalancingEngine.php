<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlTimeshareSchedule;

class HotelTimeshareExchangePoolBalancingEngine
{
    /**
     * Memverifikasi keseimbangan alokasi pertukaran musim pada pasar sekunder vila fraksional.
     *
     * @return array{schedule_count: int, peak_weeks_offered: int, regular_weeks_offered: int, pool_balance_ratio: float, is_pool_liquid: bool}
     */
    public function evaluatePoolLiquidity(): array
    {
        $schedules = HtlTimeshareSchedule::where('status', 'OWNER_OCCUPIED')->get();

        $peakCount = $schedules->where('season', 'PEAK')->count();
        $regularCount = $schedules->where('season', '!=', 'PEAK')->count();
        $total = $schedules->count();

        $ratio = $total > 0 ? round($peakCount / $total, 2) : 0.0;
        $isLiquid = ($peakCount > 0) && ($regularCount > 0);

        return [
            'schedule_count' => $total,
            'peak_weeks_offered' => $peakCount,
            'regular_weeks_offered' => $regularCount,
            'pool_balance_ratio' => $ratio,
            'is_pool_liquid' => $isLiquid,
        ];
    }
}
