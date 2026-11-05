<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

class MicroSimulationService
{
    /**
     * Simulasi perbandingan skema akad pembiayaan mikro:
     * - Mudharabah Bertingkat (Nisbah Bagi Hasil)
     * - Murabahah Mikro (Margin Tetap)
     * - Qardh al-Hasan (Kebajikan Tanpa Biaya)
     *
     * @return array{
     *   financing_amount_idr: int,
     *   tenor_months: int,
     *   mudharabah: array{monthly_installment_idr: int, expected_profit_share_idr: int, total_return_idr: int, nisbah_bps: int},
     *   murabahah: array{monthly_installment_idr: int, margin_idr: int, total_return_idr: int, margin_bps: int},
     *   qardh: array{monthly_installment_idr: int, admin_fee_idr: int, total_return_idr: int}
     * }
     */
    public function simulateComparison(
        int $financingAmountIdr,
        int $tenorMonths,
        int $expectedMonthlyRevenueIdr,
        int $mudharabahNisbahBps = 4000,
        int $murabahahMarginBps = 1200
    ): array {
        $tenorMonths = max(1, min(60, $tenorMonths));
        $principalPerMonth = (int) intdiv($financingAmountIdr, $tenorMonths);

        // 1. Mudharabah Bertingkat: Bagi hasil berbasis realisasi laba/omzet
        $monthlyExpectedProfit = (int) intdiv($expectedMonthlyRevenueIdr * 20, 100); // Estimasi marjin laba bersih 20%
        $bankProfitShareMonthly = (int) intdiv($monthlyExpectedProfit * $mudharabahNisbahBps, 10000);
        $totalMudharabahReturn = $financingAmountIdr + ($bankProfitShareMonthly * $tenorMonths);

        // 2. Murabahah Mikro: Margin proporsional
        $totalMargin = (int) intdiv($financingAmountIdr * $murabahahMarginBps * $tenorMonths, 120000);
        $totalMurabahahReturn = $financingAmountIdr + $totalMargin;
        $murabahahMonthly = (int) intdiv($totalMurabahahReturn, $tenorMonths);

        // 3. Qardh al-Hasan: Pokok kembali murni tanpa bunga/margin
        $totalQardhReturn = $financingAmountIdr;
        $qardhMonthly = $principalPerMonth;

        return [
            'financing_amount_idr' => $financingAmountIdr,
            'tenor_months' => $tenorMonths,
            'mudharabah' => [
                'monthly_installment_idr' => $principalPerMonth + $bankProfitShareMonthly,
                'expected_profit_share_idr' => $bankProfitShareMonthly * $tenorMonths,
                'total_return_idr' => $totalMudharabahReturn,
                'nisbah_bps' => $mudharabahNisbahBps,
            ],
            'murabahah' => [
                'monthly_installment_idr' => $murabahahMonthly,
                'margin_idr' => $totalMargin,
                'total_return_idr' => $totalMurabahahReturn,
                'margin_bps' => $murabahahMarginBps,
            ],
            'qardh' => [
                'monthly_installment_idr' => $qardhMonthly,
                'admin_fee_idr' => 0,
                'total_return_idr' => $totalQardhReturn,
            ],
        ];
    }
}
