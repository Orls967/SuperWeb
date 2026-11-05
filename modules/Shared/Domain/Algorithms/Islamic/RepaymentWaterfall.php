<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Algoritma B5: RepaymentWaterfall
 *
 * Alokasi angsuran pembiayaan mikro syariah berjenjang:
 *  1. Ujrah / Margin bagi hasil jatuh tempo
 *  2. Pokok pembiayaan (principal)
 *  3. Denda keterlambatan (ta'zir kebajikan, mengalir ke pos kebajikan qardh, dilarang menjadi laba BMT)
 *  4. Sisa dana masuk ke simpanan titipan (wadiah excess deposit)
 */
final class RepaymentWaterfall
{
    /**
     * @param  int  $paymentAmount  Dana pembayaran yang diterima (sen/minor unit)
     * @param  int  $dueMargin  Margin/ujrah yang jatuh tempo (sen)
     * @param  int  $duePrincipal  Pokok pembiayaan yang jatuh tempo (sen)
     * @param  int  $tazirDailyRateBps  Tarif ta'zir harian dalam basis poin (misal 50 bps = 0.5% per hari)
     * @param  int  $daysOverdue  Jumlah hari keterlambatan pembayaran
     */
    public static function allocate(
        int $paymentAmount,
        int $dueMargin,
        int $duePrincipal,
        int $tazirDailyRateBps = 0,
        int $daysOverdue = 0
    ): RepaymentWaterfallResult {
        if ($paymentAmount < 0 || $dueMargin < 0 || $duePrincipal < 0 || $tazirDailyRateBps < 0 || $daysOverdue < 0) {
            throw new InvalidArgumentException('Nilai input RepaymentWaterfall tidak boleh negatif.');
        }

        // 1. Hitung potensi denda keterlambatan (ta'zir)
        $tazirAmount = 0;
        if ($daysOverdue > 0 && $tazirDailyRateBps > 0 && $duePrincipal > 0) {
            $tazirAmount = (int) intdiv($duePrincipal * $daysOverdue * $tazirDailyRateBps, 10000);
        }

        $remaining = $paymentAmount;

        // Bucket 1: Ujrah / Margin
        $allocatedMargin = min($remaining, $dueMargin);
        $remaining -= $allocatedMargin;
        $unpaidMargin = $dueMargin - $allocatedMargin;

        // Bucket 2: Pokok Pembiayaan
        $allocatedPrincipal = min($remaining, $duePrincipal);
        $remaining -= $allocatedPrincipal;
        $unpaidPrincipal = $duePrincipal - $allocatedPrincipal;

        // Bucket 3: Ta'zir Kebajikan
        $allocatedTazir = min($remaining, $tazirAmount);
        $remaining -= $allocatedTazir;
        $unpaidTazir = $tazirAmount - $allocatedTazir;

        // Sisa lebih dana disimpan sebagai titipan nasabah
        $excessDeposit = $remaining;

        $isFullySettled = ($unpaidMargin === 0 && $unpaidPrincipal === 0 && $unpaidTazir === 0);

        $payload = [
            'algorithm' => 'B5-RepaymentWaterfall',
            'version' => '1.0',
            'inputs' => [
                'payment_amount' => $paymentAmount,
                'due_margin' => $dueMargin,
                'due_principal' => $duePrincipal,
                'tazir_daily_rate_bps' => $tazirDailyRateBps,
                'days_overdue' => $daysOverdue,
            ],
            'outputs' => [
                'allocated_margin' => $allocatedMargin,
                'allocated_principal' => $allocatedPrincipal,
                'allocated_tazir' => $allocatedTazir,
                'excess_deposit' => $excessDeposit,
                'unpaid_margin' => $unpaidMargin,
                'unpaid_principal' => $unpaidPrincipal,
                'unpaid_tazir' => $unpaidTazir,
                'is_fully_settled' => $isFullySettled,
            ],
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ];

        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $certificate = [
            'payload' => $payload,
            'hash' => $hash,
            'created_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        return new RepaymentWaterfallResult(
            allocatedMargin: $allocatedMargin,
            allocatedPrincipal: $allocatedPrincipal,
            allocatedTazir: $allocatedTazir,
            excessDeposit: $excessDeposit,
            unpaidMargin: $unpaidMargin,
            unpaidPrincipal: $unpaidPrincipal,
            unpaidTazir: $unpaidTazir,
            isFullySettled: $isFullySettled,
            certificate: $certificate
        );
    }
}

