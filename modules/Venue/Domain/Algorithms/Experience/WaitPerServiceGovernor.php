<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Experience;

/**
 * Algoritma V15: WaitPerServiceGovernor.
 * Mengestimasi waktu tunggu antrean layanan bar dan F&B menggunakan pendekatan antrean M/M/c deterministik,
 * menghitung skor kepuasan layanan (WPS), dan memicu sinyal bantuan shift kasir (reinforcement) saat antrean jenuh.
 */
final class WaitPerServiceGovernor
{
    /**
     * Hitung skor WPS dan kebutuhan penambahan staf.
     */
    public function calculateWps(
        int $stationId,
        int $activeServersCount,
        int $arrivalRatePerMinuteBps,
        int $serviceRatePerMinuteBps,
        int $maxTolerableWaitMinutes = 15,
        string $saltSecret = 'WPS_GOVERNOR_SALT_2026'
    ): WpsGovernorResult {
        $serversSafe = max(1, $activeServersCount);
        $totalServiceCapacityBps = $serversSafe * max(1, $serviceRatePerMinuteBps);

        // 1. Rasio Utilisasi Sistem (\rho)
        $utilizationBps = (int) intdiv($arrivalRatePerMinuteBps * 10000, $totalServiceCapacityBps);

        // 2. Estimasi Waktu Tunggu Antrean (Menit)
        if ($utilizationBps >= 10000) {
            // Sistem kelebihan beban (saturated)
            $avgWaitMinutes = $maxTolerableWaitMinutes * 2;
            $wpsScore = 0;
            $reinforcementNeeded = true;
            $additionalNeeded = max(1, (int) ceil(($arrivalRatePerMinuteBps - $totalServiceCapacityBps) / max(1, $serviceRatePerMinuteBps)) + 1);
        } else {
            // Teori antrean M/M/c: W_q ~ \rho / (1 - \rho) * (1 / (c * \mu))
            $serviceRateFloat = max(1, intdiv($serviceRatePerMinuteBps, 1000)); // misalnya 2 pesanan/menit
            $oneMinusRho = max(1, 10000 - $utilizationBps);
            $waitMinutesFloat = ($utilizationBps / $oneMinusRho) * (1.0 / ($serversSafe * $serviceRateFloat));
            $avgWaitMinutes = max(0, (int) round($waitMinutesFloat));
            $wpsScore = max(0, 100 - (int) intdiv($avgWaitMinutes * 100, max(1, $maxTolerableWaitMinutes)));
            $reinforcementNeeded = $wpsScore < 60 || $utilizationBps >= 8500;
            $additionalNeeded = $reinforcementNeeded ? 1 : 0;
        }

        $now = time();
        $payload = sprintf(
            '%d:%d:%d:%d:%d:%d:%d',
            $stationId,
            $avgWaitMinutes,
            $utilizationBps,
            $wpsScore,
            $reinforcementNeeded ? 1 : 0,
            $additionalNeeded,
            $now
        );
        $signature = hash_hmac('sha256', $payload, $saltSecret);

        return new WpsGovernorResult(
            stationId: $stationId,
            averageWaitTimeMinutes: $avgWaitMinutes,
            utilizationRateBps: $utilizationBps,
            wpsScore: $wpsScore,
            reinforcementNeeded: $reinforcementNeeded,
            recommendedAdditionalServers: $additionalNeeded,
            certificateSignature: $signature,
            calculatedAtTimestamp: $now
        );
    }
}
