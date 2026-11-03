<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Logistics\Contracts\DemurrageDetentionQuote;

final class LogisticsDemurrageDetentionQuote implements DemurrageDetentionQuote
{
    /**
     * Mengutip biaya Demurrage dan Detention berdasarkan durasi dwell (hari kalender).
     */
    public function quote(int $containerId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $freeDays = 5;
        $totalDays = max(0, (int) $from->diffInDays($to));
        $chargeableDays = max(0, $totalDays - $freeDays);

        // Tarif harian standar: Demurrage Rp 350.000 / hari, Detention Rp 250.000 / hari
        $demurrageDailyRate = 350_000;
        $detentionDailyRate = 250_000;

        $demurrageIdr = $chargeableDays * $demurrageDailyRate;
        $detentionIdr = $chargeableDays * $detentionDailyRate;

        return [
            'demurrage_idr' => $demurrageIdr,
            'detention_idr' => $detentionIdr,
            'free_days' => $freeDays,
        ];
    }
}
