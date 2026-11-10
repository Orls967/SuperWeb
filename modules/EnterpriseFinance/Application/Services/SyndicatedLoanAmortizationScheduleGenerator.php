<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use InvalidArgumentException;
use Modules\EnterpriseFinance\Domain\Models\SyndicatedFacility;

/**
 * Generator Jadwal Amortisasi Pinjaman Sindikasi Multi-Bank (54.7 - LMA Standards).
 * Menghasilkan proyeksi angsuran pokok periodik (Equal Principal Repayment),
 * perhitungan beban bunga mengambang (Benchmark BI-Rate + Spread BPS),
 * dan commitment fee atas fasilitas yang belum ditarik (undrawn commitment).
 */
class SyndicatedLoanAmortizationScheduleGenerator
{
    /**
     * Commitment fee tahunan standar atas fasilitas belum ditarik (50 bps = 0.50%).
     */
    public const UNDRAWN_COMMITMENT_FEE_BPS = 50;

    /**
     * Menghasilkan tabel jadwal amortisasi cicilan per kuartal.
     *
     * @param  int  $quartersCount  Jumlah kuartal tenor pinjaman
     * @param  int  $benchmarkRateBps  Suku bunga acuan pasar (mis. 600 bps = 6.00%)
     * @return array{
     *     facility_code: string,
     *     currency: string,
     *     principal_total_minor: int,
     *     total_quarters: int,
     *     total_interest_projected_minor: int,
     *     total_service_projected_minor: int,
     *     schedule: list<array{
     *         quarter: int,
     *         opening_principal_minor: int,
     *         principal_payment_minor: int,
     *         interest_rate_effective_bps: int,
     *         interest_payment_minor: int,
     *         undrawn_fee_minor: int,
     *         total_installment_minor: int,
     *         closing_principal_minor: int
     *     }>
     * }
     */
    public function generateQuarterlySchedule(
        SyndicatedFacility $facility,
        int $quartersCount = 12,
        int $benchmarkRateBps = 600
    ): array {
        if ($quartersCount <= 0) {
            throw new InvalidArgumentException('Jumlah kuartal amortisasi harus lebih besar dari nol.');
        }

        $totalDrawn = $facility->drawn_amount_minor > 0
            ? $facility->drawn_amount_minor
            : $facility->total_commitment_minor;

        $principalPerQuarter = (int) intdiv($totalDrawn, $quartersCount);
        $spreadBps = (int) $facility->spread_bps;
        $effectiveRateBps = $benchmarkRateBps + $spreadBps;

        $runningPrincipal = $totalDrawn;
        $schedule = [];
        $totalInterest = 0;

        for ($q = 1; $q <= $quartersCount; $q++) {
            $opening = $runningPrincipal;
            $principalPayment = ($q === $quartersCount) ? $runningPrincipal : $principalPerQuarter;

            // Beban bunga kuartalan = Pokok Awal * (Suku Bunga Efektif BPS / 10000) * (90 / 360)
            $interestPayment = (int) intdiv($opening * $effectiveRateBps, 10000 * 4);
            $totalInterest += $interestPayment;

            // Biaya komitmen atas pagu belum ditarik (jika ada)
            $undrawn = max(0, $facility->total_commitment_minor - $opening);
            $undrawnFee = (int) intdiv($undrawn * self::UNDRAWN_COMMITMENT_FEE_BPS, 10000 * 4);

            $closing = max(0, $opening - $principalPayment);
            $runningPrincipal = $closing;

            $totalInstallment = $principalPayment + $interestPayment + $undrawnFee;

            $schedule[] = [
                'quarter' => $q,
                'opening_principal_minor' => $opening,
                'principal_payment_minor' => $principalPayment,
                'interest_rate_effective_bps' => $effectiveRateBps,
                'interest_payment_minor' => $interestPayment,
                'undrawn_fee_minor' => $undrawnFee,
                'total_installment_minor' => $totalInstallment,
                'closing_principal_minor' => $closing,
            ];
        }

        return [
            'facility_code' => $facility->facility_code,
            'currency' => $facility->currency ?? 'IDR',
            'principal_total_minor' => $totalDrawn,
            'total_quarters' => $quartersCount,
            'total_interest_projected_minor' => $totalInterest,
            'total_service_projected_minor' => $totalDrawn + $totalInterest,
            'schedule' => $schedule,
        ];
    }
}
