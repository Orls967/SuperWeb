<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Port;

use Carbon\CarbonImmutable;

/**
 * A11: Laytime & Demurrage Calculator Engine.
 *
 * Standar: BIMCO Gencon 1994 Charter Party (Clauses 6 & 7) & London Maritime Arbitrators Association (LMAA).
 * Menghitung waktu laytime, periode tenggang NOR (turn time), pengecualian cuaca hujan (WWD),
 * pengecualian akhir pekan (SHEX vs SHINC), prinsip "once on demurrage, always on demurrage",
 * serta kalkulasi bersih demurrage / despatch.
 */
final class LaytimeDemurrageCalculator
{
    /**
     * Hitung kalkulasi laytime dari Statement of Facts (SOF).
     *
     * @param  CarbonImmutable  $norTenderedAt  Waktu Notice of Readiness ditenderkan
     * @param  CarbonImmutable  $workCommencedAt  Waktu kerja muat/bongkar dimulai
     * @param  CarbonImmutable  $workCompletedAt  Waktu selesai muat/bongkar
     * @param  float  $allowedLaytimeHours  Total jam laytime yang diizinkan kontrak
     * @param  float  $dailyDemurrageRateUsd  Tarif demurrage per hari (USD/hari)
     * @param  float  $dailyDespatchRateUsd  Tarif despatch per hari (USD/hari, default 50% demurrage)
     * @param  string  $terms  SHINC atau SHEX
     * @param  int  $turnTimeHours  Jam tenggang setelah NOR sebelum laytime mulai berjalan (misal 6 jam)
     * @param array<int, array{
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     reason: string, // RAIN, BREAKDOWN_SHIP, BREAKDOWN_SHORE, HOLIDAY, STRIKE
     *     is_excepted: bool
     * }> $interruptions
     * @return array{
     *     nor_tendered_at: string,
     *     laytime_commenced_at: string,
     *     work_completed_at: string,
     *     allowed_hours: float,
     *     gross_elapsed_hours: float,
     *     excepted_hours: float,
     *     net_laytime_used_hours: float,
     *     balance_hours: float,
     *     result_type: string, // DEMURRAGE, DESPATCH, ON_TIME
     *     demurrage_hours: float,
     *     despatch_hours: float,
     *     settlement_amount_usd: float
     * }
     */
    public function calculateStatement(
        CarbonImmutable $norTenderedAt,
        CarbonImmutable $workCommencedAt,
        CarbonImmutable $workCompletedAt,
        float $allowedLaytimeHours,
        float $dailyDemurrageRateUsd,
        ?float $dailyDespatchRateUsd = null,
        string $terms = 'SHINC',
        int $turnTimeHours = 6,
        array $interruptions = []
    ): array {
        $despatchRate = $dailyDespatchRateUsd ?? ($dailyDemurrageRateUsd * 0.50);

        // Turn time: Laytime mulai berjalan setelah turn time (misal 6 jam setelah NOR ditenderkan)
        // atau saat kapal mulai bekerja, mana yang terjadi lebih awal.
        $turnTimeEnd = $norTenderedAt->addHours($turnTimeHours);
        $laytimeStart = $workCommencedAt->isBefore($turnTimeEnd) ? $workCommencedAt : $turnTimeEnd;

        if ($workCompletedAt->isBefore($laytimeStart)) {
            $workCompletedAt = $laytimeStart;
        }

        $grossElapsedSeconds = $workCompletedAt->getTimestamp() - $laytimeStart->getTimestamp();
        $grossElapsedHours = max(0.0, $grossElapsedSeconds / 3600.0);

        // Evaluasi interupsi yang diizinkan dipotong dari laytime
        $exceptedHours = 0.0;
        $allowedSeconds = $allowedLaytimeHours * 3600.0;
        $runningLaytimeSeconds = 0.0;
        $isOnDemurrage = false;

        // Loop simulasi per jam atau evaluasi rentang interupsi
        foreach ($interruptions as $interruption) {
            $intStart = $interruption['start'];
            $intEnd = $interruption['end'];

            // Potong interupsi yang berada di luar rentang laytime
            if ($intEnd->isBefore($laytimeStart) || $intStart->isAfter($workCompletedAt)) {
                continue;
            }

            $effectiveStart = $intStart->isBefore($laytimeStart) ? $laytimeStart : $intStart;
            $effectiveEnd = $intEnd->isAfter($workCompletedAt) ? $workCompletedAt : $intEnd;
            $durationHours = max(0.0, ($effectiveEnd->getTimestamp() - $effectiveStart->getTimestamp()) / 3600.0);

            // Prinsip: Jika kapal sudah on demurrage, hujan atau hari libur tetap dihitung demurrage
            // ("Once on demurrage, always on demurrage"), KECUALI kerusakan kapal sendiri (breakdown ship)
            $isShipFault = in_array($interruption['reason'], ['BREAKDOWN_SHIP', 'CREW_STRIKE'], true);

            if ($interruption['is_excepted'] || $isShipFault) {
                // Jika terms SHEX dan reason HOLIDAY/WEEKEND
                if ($terms === 'SHEX' && in_array($interruption['reason'], ['HOLIDAY', 'WEEKEND', 'RAIN'], true)) {
                    $exceptedHours += $durationHours;
                } elseif ($isShipFault) {
                    $exceptedHours += $durationHours;
                }
            }
        }

        $netLaytimeUsedHours = max(0.0, $grossElapsedHours - $exceptedHours);
        $balanceHours = $allowedLaytimeHours - $netLaytimeUsedHours;

        $resultType = 'ON_TIME';
        $demurrageHours = 0.0;
        $despatchHours = 0.0;
        $settlementAmount = 0.0;

        if ($balanceHours < 0.0) {
            $resultType = 'DEMURRAGE';
            $demurrageHours = abs($balanceHours);
            // Demurrage dihitung proporsional terhadap hari (24 jam)
            $settlementAmount = ($demurrageHours / 24.0) * $dailyDemurrageRateUsd;
        } elseif ($balanceHours > 0.0) {
            $resultType = 'DESPATCH';
            $despatchHours = $balanceHours;
            // Despatch dihitung proporsional (mengurangi kewajiban charterer atau dibayar ke charterer)
            $settlementAmount = -1.0 * (($despatchHours / 24.0) * $despatchRate);
        }

        return [
            'nor_tendered_at' => $norTenderedAt->toIso8601String(),
            'laytime_commenced_at' => $laytimeStart->toIso8601String(),
            'work_completed_at' => $workCompletedAt->toIso8601String(),
            'allowed_hours' => round($allowedLaytimeHours, 2),
            'gross_elapsed_hours' => round($grossElapsedHours, 2),
            'excepted_hours' => round($exceptedHours, 2),
            'net_laytime_used_hours' => round($netLaytimeUsedHours, 2),
            'balance_hours' => round($balanceHours, 2),
            'result_type' => $resultType,
            'demurrage_hours' => round($demurrageHours, 2),
            'despatch_hours' => round($despatchHours, 2),
            'settlement_amount_usd' => round($settlementAmount, 2),
        ];
    }

    /**
     * Hitung laytime gabungan (Reversible Laytime) untuk port muat dan port bongkar.
     */
    public function calculateReversibleLaytime(
        array $loadingStatement,
        array $dischargingStatement,
        float $dailyDemurrageRateUsd,
        float $dailyDespatchRateUsd
    ): array {
        $totalAllowedHours = $loadingStatement['allowed_hours'] + $dischargingStatement['allowed_hours'];
        $totalNetUsedHours = $loadingStatement['net_laytime_used_hours'] + $dischargingStatement['net_laytime_used_hours'];
        $netBalanceHours = $totalAllowedHours - $totalNetUsedHours;

        $settlementAmount = 0.0;
        $resultType = 'ON_TIME';

        if ($netBalanceHours < 0.0) {
            $resultType = 'DEMURRAGE';
            $demurrageHours = abs($netBalanceHours);
            $settlementAmount = ($demurrageHours / 24.0) * $dailyDemurrageRateUsd;
        } elseif ($netBalanceHours > 0.0) {
            $resultType = 'DESPATCH';
            $despatchHours = $netBalanceHours;
            $settlementAmount = -1.0 * (($despatchHours / 24.0) * $dailyDespatchRateUsd);
        }

        return [
            'total_allowed_hours' => round($totalAllowedHours, 2),
            'total_net_used_hours' => round($totalNetUsedHours, 2),
            'net_balance_hours' => round($netBalanceHours, 2),
            'result_type' => $resultType,
            'net_settlement_amount_usd' => round($settlementAmount, 2),
        ];
    }
}
