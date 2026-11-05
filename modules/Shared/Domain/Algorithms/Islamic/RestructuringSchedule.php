<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use InvalidArgumentException;

/**
 * Algoritma B9: RestructuringSchedule
 *
 * Menghitung amortisasi restrukturisasi pembiayaan syariah (Murabahah/Ijarah)
 * murni melalui rescheduling & reconditioning tanpa penambahan margin baru (bebas riba).
 */
final class RestructuringSchedule
{
    /**
     * Hitung jadwal restrukturisasi syariah bersertifikat.
     */
    public static function calculate(
        string $financingRef,
        int $originalOutstandingPrincipalMinor,
        int $originalRemainingMarginMinor,
        int $marginHaircutBps,
        int $newTenorMonths,
        int $gracePeriodMonths = 0,
        int $restructuringFeeMinor = 0
    ): RestructuringScheduleResult {
        if ($originalOutstandingPrincipalMinor <= 0) {
            throw new InvalidArgumentException('Original principal must be positive.');
        }

        if ($originalRemainingMarginMinor < 0) {
            throw new InvalidArgumentException('Original margin cannot be negative.');
        }

        if ($marginHaircutBps < 0 || $marginHaircutBps > 10000) {
            throw new InvalidArgumentException('Margin haircut bps must be between 0 and 10000.');
        }

        if ($newTenorMonths <= 0) {
            throw new InvalidArgumentException('New tenor must be at least 1 month.');
        }

        if ($gracePeriodMonths < 0 || $gracePeriodMonths >= $newTenorMonths) {
            throw new InvalidArgumentException('Grace period must be non-negative and less than total new tenor.');
        }

        if ($restructuringFeeMinor < 0) {
            throw new InvalidArgumentException('Restructuring fee cannot be negative.');
        }

        // Hitung margin setelah haircut (tanazul): margin baru tidak boleh lebih dari margin lama
        $haircutAmount = (int) intval(round(($originalRemainingMarginMinor * $marginHaircutBps) / 10000.0));
        $revisedMarginMinor = max(0, $originalRemainingMarginMinor - $haircutAmount);
        $revisedPrincipalMinor = $originalOutstandingPrincipalMinor;

        $totalRestructuredDebtMinor = $revisedPrincipalMinor + $revisedMarginMinor;

        $activeAmortizationMonths = $newTenorMonths - $gracePeriodMonths;

        // Hitung cicilan bulanan linear
        $baseMonthlyPrincipal = intdiv($revisedPrincipalMinor, $activeAmortizationMonths);
        $remPrincipal = $revisedPrincipalMinor % $activeAmortizationMonths;

        $baseMonthlyMargin = intdiv($revisedMarginMinor, $activeAmortizationMonths);
        $remMargin = $revisedMarginMinor % $activeAmortizationMonths;

        $scheduleTable = [];
        $runningPrincipal = $revisedPrincipalMinor;
        $runningMargin = $revisedMarginMinor;

        for ($m = 1; $m <= $newTenorMonths; $m++) {
            $isGrace = ($m <= $gracePeriodMonths);

            if ($isGrace) {
                $monthPrincipal = 0;
                $monthMargin = 0;
                $installment = 0;
            } else {
                $activeIdx = $m - $gracePeriodMonths;
                $monthPrincipal = $baseMonthlyPrincipal + ($activeIdx <= $remPrincipal ? 1 : 0);
                $monthMargin = $baseMonthlyMargin + ($activeIdx <= $remMargin ? 1 : 0);
                $installment = $monthPrincipal + $monthMargin;

                $runningPrincipal -= $monthPrincipal;
                $runningMargin -= $monthMargin;
            }

            $scheduleTable[] = [
                'month' => $m,
                'is_grace' => $isGrace,
                'principal_minor' => $monthPrincipal,
                'margin_minor' => $monthMargin,
                'installment_minor' => $installment,
                'remaining_principal_minor' => max(0, $runningPrincipal),
                'remaining_margin_minor' => max(0, $runningMargin),
            ];
        }

        $representativeMonthlyPrincipal = $activeAmortizationMonths > 0 ? intdiv($revisedPrincipalMinor, $activeAmortizationMonths) : 0;
        $representativeMonthlyMargin = $activeAmortizationMonths > 0 ? intdiv($revisedMarginMinor, $activeAmortizationMonths) : 0;
        $representativeMonthlyTotal = $representativeMonthlyPrincipal + $representativeMonthlyMargin;

        $certificatePayload = json_encode([
            'financing_ref' => $financingRef,
            'original_principal' => $originalOutstandingPrincipalMinor,
            'original_margin' => $originalRemainingMarginMinor,
            'haircut_bps' => $marginHaircutBps,
            'revised_principal' => $revisedPrincipalMinor,
            'revised_margin' => $revisedMarginMinor,
            'new_tenor' => $newTenorMonths,
            'grace_period' => $gracePeriodMonths,
            'schedule_count' => count($scheduleTable),
        ], JSON_THROW_ON_ERROR);

        $certificateHash = hash('sha256', $certificatePayload);

        return new RestructuringScheduleResult(
            financingRef: $financingRef,
            originalPrincipalMinor: $originalOutstandingPrincipalMinor,
            originalMarginMinor: $originalRemainingMarginMinor,
            marginHaircutBps: $marginHaircutBps,
            revisedPrincipalMinor: $revisedPrincipalMinor,
            revisedMarginMinor: $revisedMarginMinor,
            totalRestructuredDebtMinor: $totalRestructuredDebtMinor,
            newTenorMonths: $newTenorMonths,
            gracePeriodMonths: $gracePeriodMonths,
            monthlyPrincipalInstallmentMinor: $representativeMonthlyPrincipal,
            monthlyMarginInstallmentMinor: $representativeMonthlyMargin,
            monthlyTotalInstallmentMinor: $representativeMonthlyTotal,
            scheduleTable: $scheduleTable,
            certificateHash: $certificateHash,
        );
    }
}
