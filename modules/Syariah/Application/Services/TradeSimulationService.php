<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class TradeSimulationService
{
    /**
     * Membandingkan biaya dan yield struktur perdagangan syariah:
     * - Murabahah Supply Chain Finance (pembelian bahan baku bertempo)
     * - Salam Komoditas (pembayaran tunai di muka + parallel hedge)
     * - Istisna Manufaktur (pesanan manufaktur bertahap dengan cost-to-cost POC)
     *
     * @return array<string, mixed>
     */
    public function simulateComparison(
        int $principalAmountCents,
        int $tenorDays,
        int $annualMarginRateBps,
        int $spotFxRateBps = 1_550_000
    ): array {
        if ($principalAmountCents <= 0 || $tenorDays <= 0 || $annualMarginRateBps <= 0) {
            throw new InvalidArgumentException('Parameter simulasi harus bernilai positif.');
        }

        // 1. Murabahah SCF
        $scfProfit = (int) intdiv($principalAmountCents * $annualMarginRateBps * $tenorDays, 365 * 10000);
        $scfTotalReceivable = $principalAmountCents + $scfProfit;
        $scfMonthlyPayment = (int) intdiv($scfTotalReceivable, max(1, (int) intdiv($tenorDays, 30)));

        // 2. Salam Komoditas (Discounted advance payment to producer)
        // Advance payment diberikan lebih rendah dibanding estimasi harga serah komoditas
        $salamDiscountBps = (int) intdiv($annualMarginRateBps * $tenorDays, 365);
        $salamExpectedOfftakePrice = $principalAmountCents;
        $salamAdvancePayment = (int) intdiv($salamExpectedOfftakePrice * 10000, 10000 + $salamDiscountBps);
        $salamGrossMargin = $salamExpectedOfftakePrice - $salamAdvancePayment;

        // 3. Istisna Manufaktur (Progressive cost-to-cost milestone)
        $istisnaContractPrice = (int) intdiv($principalAmountCents * (10000 + (int) intdiv($annualMarginRateBps * $tenorDays, 365)), 10000);
        $istisnaBudgetedCost = $principalAmountCents;
        $istisnaEstimatedMargin = $istisnaContractPrice - $istisnaBudgetedCost;

        // 4. Tawarruq FX Equivalent
        $targetCurrencyUnits = (int) intdiv($principalAmountCents * 10000, $spotFxRateBps);
        $fxMarkupProfit = (int) intdiv($principalAmountCents * $annualMarginRateBps * $tenorDays, 365 * 10000);
        $fxDeferredPrice = $principalAmountCents + $fxMarkupProfit + 2_500_000;
        $fxEffectiveRate = (int) intdiv($fxDeferredPrice * 10000, max(1, $targetCurrencyUnits));

        return [
            'simulated_at' => CarbonImmutable::now()->toIso8601String(),
            'inputs' => [
                'principal_amount_cents' => $principalAmountCents,
                'tenor_days' => $tenorDays,
                'annual_margin_rate_bps' => $annualMarginRateBps,
                'annual_margin_percent' => sprintf('%.2f%%', $annualMarginRateBps / 100),
                'spot_fx_rate_bps' => $spotFxRateBps,
            ],
            'scf_murabahah' => [
                'contract_type' => 'MURABAHAH_SCF',
                'cost_basis_cents' => $principalAmountCents,
                'margin_profit_cents' => $scfProfit,
                'total_receivable_cents' => $scfTotalReceivable,
                'estimated_monthly_cents' => $scfMonthlyPayment,
                'shariah_notes' => 'Keuntungan disepakati transparan di awal akad, bebas bunga majemuk atas keterlambatan.',
            ],
            'salam_commodity' => [
                'contract_type' => 'SALAM_PARALLEL_HEDGE',
                'advance_paid_to_producer_cents' => $salamAdvancePayment,
                'expected_offtake_sale_cents' => $salamExpectedOfftakePrice,
                'gross_hedged_margin_cents' => $salamGrossMargin,
                'shariah_notes' => 'Pembayaran tunai penuh 100% di muka kepada produsen, di-hedge via parallel salam off-taker.',
            ],
            'istisna_manufacturing' => [
                'contract_type' => 'ISTISNA_POC',
                'budgeted_cost_cents' => $istisnaBudgetedCost,
                'agreed_contract_price_cents' => $istisnaContractPrice,
                'expected_margin_cents' => $istisnaEstimatedMargin,
                'shariah_notes' => 'Pengakuan pendapatan bertahap mengikuti metode persentase penyelesaian fisik/biaya aktual.',
            ],
            'tawarruq_fx' => [
                'contract_type' => 'TAWARRUQ_MUNAZZAM',
                'source_principal_cents' => $principalAmountCents,
                'target_valas_received_cents' => $targetCurrencyUnits,
                'markup_profit_cents' => $fxMarkupProfit,
                'total_deferred_price_cents' => $fxDeferredPrice,
                'effective_rate_bps' => $fxEffectiveRate,
                'shariah_notes' => 'Konversi valuta asing dan lindung nilai via transaksi lot komoditas bursa fisik riil anti-inah.',
            ],
        ];
    }
}
