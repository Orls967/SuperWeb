<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Syariah\Contracts\GigPayoutHistory;

class SyariahGigPayoutHistoryService implements GigPayoutHistory
{
    /**
     * @return array<int, array{month: string, gross_amount: int, net_payout: int, tasks_completed: int}>
     */
    public function getMonthlyPayoutHistory(string $workerPartyId, int $months = 6): array
    {
        $months = max(3, min(24, $months));
        $history = [];
        $now = CarbonImmutable::now();

        // Seed deterministik berbasis hash ID worker agar histori stabil dan dapat diuji berulang
        $seedInt = abs(crc32($workerPartyId)) % 1000;
        $baseIncome = 350000000 + ($seedInt * 100000); // ~ Rp 3.5jt - Rp 4.5jt

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthDate = $now->subMonths($i);
            $monthKey = $monthDate->format('Y-m');

            // Variasi bulanan deterministik (+/- 15%)
            $variationBps = (($seedInt + ($i * 37)) % 300) - 150; // -150 s.d +150 bps
            $gross = (int) ($baseIncome * (10000 + $variationBps) / 10000);
            $net = (int) ($gross * 95 / 100); // Potongan platform 5%
            $tasks = 8 + (($seedInt + $i) % 12);

            $history[] = [
                'month' => $monthKey,
                'gross_amount' => $gross,
                'net_payout' => $net,
                'tasks_completed' => $tasks,
            ];
        }

        return $history;
    }

    public function getAmanahScore(string $workerPartyId): int
    {
        $seedInt = abs(crc32($workerPartyId)) % 100;

        // Skor amanah deterministik antara 65 dan 98
        return 65 + (int) ($seedInt * 33 / 100);
    }
}

