<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services;

use Carbon\CarbonInterface;
use Modules\Logistics\Domain\Models\DdTariff;

/**
 * Hitung demurrage/detention. Hari dihitung per tanggal kalender pada zona waktu lokasi
 * (hari mulai = hari ke-1), free time dikurangkan, sisanya ditagih per hari dengan eskalasi opsional.
 */
class DwellChargeCalculator
{
    public function daysElapsed(CarbonInterface $startedAt, CarbonInterface $asOf, string $timezone): int
    {
        $start = $startedAt->copy()->setTimezone($timezone)->startOfDay();
        $end = $asOf->copy()->setTimezone($timezone)->startOfDay();

        if ($end->lessThan($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }

    public function billableDays(int $elapsedDays, DdTariff $tariff): int
    {
        return max(0, $elapsedDays - $tariff->free_days);
    }

    public function amountFor(int $billableDays, DdTariff $tariff): int
    {
        if ($billableDays <= 0) {
            return 0;
        }

        $escalateAfter = $tariff->escalation_after_days;
        $escalatedRate = $tariff->escalated_rate_per_day_idr;

        if ($escalateAfter === null || $escalatedRate === null || $billableDays <= $escalateAfter) {
            return $billableDays * $tariff->rate_per_day_idr;
        }

        return $escalateAfter * $tariff->rate_per_day_idr + ($billableDays - $escalateAfter) * $escalatedRate;
    }
}
