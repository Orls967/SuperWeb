<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Encumbrance;

use InvalidArgumentException;

/**
 * Algoritma Analisis Deviasi Anggaran, Estimasi Laju Penyerapan (Burn Rate), dan Reallokasi Pagu.
 * Seluruh kalkulasi beroperasi pada bilangan bulat minor unit (sen) tanpa tipe data float (KONSEP §A3).
 */
final class BudgetVarianceAnalyzer
{
    /**
     * Menghitung status utilisasi anggaran empat dimensi.
     *
     * @param int $allocatedMinor Plafon pagu disetujui dalam minor IDR.
     * @param int $encumberedMinor Pagu yang sedang dicadangkan (reserved) untuk PO terbuka.
     * @param int $spentMinor Realisasi belanja aktual yang telah difakturkan.
     * @return array{
     *     allocated_minor: int,
     *     encumbered_minor: int,
     *     spent_minor: int,
     *     committed_total_minor: int,
     *     available_minor: int,
     *     utilization_bps: int,
     *     is_exhausted: bool,
     *     status: string
     * }
     */
    public static function analyzeUtilization(int $allocatedMinor, int $encumberedMinor, int $spentMinor): array
    {
        if ($allocatedMinor < 0 || $encumberedMinor < 0 || $spentMinor < 0) {
            throw new InvalidArgumentException('Parameter anggaran dilarang memuat nilai negatif.');
        }

        $committedTotal = $encumberedMinor + $spentMinor;
        $available = $allocatedMinor - $committedTotal;

        $utilizationBps = $allocatedMinor > 0
            ? intdiv($committedTotal * 10000, $allocatedMinor)
            : 10000;

        $status = match (true) {
            $available < 0 => 'OVER_BUDGET',
            $available === 0 => 'FULLY_EXHAUSTED',
            $utilizationBps >= 9000 => 'CRITICAL_THRESHOLD', // >= 90%
            $utilizationBps >= 7500 => 'WARNING_THRESHOLD',  // >= 75%
            default => 'HEALTHY',
        };

        return [
            'allocated_minor' => $allocatedMinor,
            'encumbered_minor' => $encumberedMinor,
            'spent_minor' => $spentMinor,
            'committed_total_minor' => $committedTotal,
            'available_minor' => $available,
            'utilization_bps' => $utilizationBps,
            'is_exhausted' => ($available <= 0),
            'status' => $status,
        ];
    }

    /**
     * Memprediksi laju penyerapan anggaran hingga akhir tahun fiskal (Linear Burn-rate Runout).
     *
     * @param int $allocatedMinor Alokasi pagu tahunan.
     * @param int $spentMinor Belanja aktual hingga hari ini.
     * @param int $elapsedDays Jumlah hari yang telah berlalu dalam tahun fiskal (1 s.d. 365).
     * @param int $totalYearDays Total hari dalam tahun fiskal (365 atau 366).
     * @return array{
     *     daily_burn_rate_minor: int,
     *     projected_annual_spend_minor: int,
     *     projected_variance_minor: int,
     *     estimated_depletion_day: int|null,
     *     is_deficit_projected: bool
     * }
     */
    public static function projectAnnualRunRate(
        int $allocatedMinor,
        int $spentMinor,
        int $elapsedDays,
        int $totalYearDays = 365
    ): array {
        if ($elapsedDays <= 0 || $totalYearDays <= 0) {
            throw new InvalidArgumentException('Jumlah hari evaluasi wajib lebih besar dari nol.');
        }

        $effectiveDays = min($elapsedDays, $totalYearDays);
        $dailyBurn = intdiv($spentMinor, $effectiveDays);
        $projectedAnnual = $dailyBurn * $totalYearDays;
        $projectedVariance = $allocatedMinor - $projectedAnnual;
        $isDeficit = ($projectedVariance < 0);

        $depletionDay = null;
        if ($dailyBurn > 0) {
            $rawDay = intdiv($allocatedMinor, $dailyBurn);
            if ($rawDay <= $totalYearDays) {
                $depletionDay = max(1, $rawDay);
            }
        }

        return [
            'daily_burn_rate_minor' => $dailyBurn,
            'projected_annual_spend_minor' => $projectedAnnual,
            'projected_variance_minor' => $projectedVariance,
            'estimated_depletion_day' => $depletionDay,
            'is_deficit_projected' => $isDeficit,
        ];
    }

    /**
     * Menilai kelayakan pergeseran pagu (reallokasi anggaran) dari mata anggaran donor ke penerima.
     *
     * @param int $donorAllocatedMinor
     * @param int $donorCommittedMinor
     * @param int $transferAmountMinor
     * @return array{
     *     is_transfer_feasible: bool,
     *     donor_available_after_minor: int,
     *     max_transferable_minor: int,
     *     rejection_reason: string|null
     * }
     */
    public static function evaluateReallocationFeasibility(
        int $donorAllocatedMinor,
        int $donorCommittedMinor,
        int $transferAmountMinor
    ): array {
        if ($transferAmountMinor <= 0) {
            return [
                'is_transfer_feasible' => false,
                'donor_available_after_minor' => 0,
                'max_transferable_minor' => 0,
                'rejection_reason' => 'Nominal transfer anggaran harus lebih besar dari nol.',
            ];
        }

        $donorAvailable = $donorAllocatedMinor - $donorCommittedMinor;
        if ($donorAvailable <= 0) {
            return [
                'is_transfer_feasible' => false,
                'donor_available_after_minor' => $donorAvailable,
                'max_transferable_minor' => 0,
                'rejection_reason' => 'Mata anggaran donor tidak memiliki sisa pagu bebas yang dapat digeser.',
            ];
        }

        if ($transferAmountMinor > $donorAvailable) {
            return [
                'is_transfer_feasible' => false,
                'donor_available_after_minor' => $donorAvailable - $transferAmountMinor,
                'max_transferable_minor' => $donorAvailable,
                'rejection_reason' => 'Permintaan pergeseran pagu melebihi sisa pagu bebas mata anggaran donor.',
            ];
        }

        return [
            'is_transfer_feasible' => true,
            'donor_available_after_minor' => $donorAvailable - $transferAmountMinor,
            'max_transferable_minor' => $donorAvailable,
            'rejection_reason' => null,
        ];
    }
}

