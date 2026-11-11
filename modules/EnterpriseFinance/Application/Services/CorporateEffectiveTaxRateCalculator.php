<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

/**
 * Kalkulator Tarif Pajak Efektif Konsolidasi Grup (Corporate ETR Engine) (54.3).
 * Menganalisis deviasi antara Tarif Pajak Statuter (22%) dengan Tarif Pajak Efektif
 * yang dibayarkan seluruh entitas grup, mengidentifikasi celah efisiensi beban pajak (Tax Optimization).
 */
class CorporateEffectiveTaxRateCalculator
{
    /**
     * Menghitung ETR konsolidasi lintas entitas anak grup.
     *
     * @param  list<array{entity_code: string, pre_tax_profit_idr: int, tax_expense_idr: int}>  $entitiesTaxData
     * @return array{
     *     total_pre_tax_profit_idr: int,
     *     total_tax_expense_idr: int,
     *     consolidated_etr_bps: int,
     *     statutory_tax_rate_bps: int,
     *     tax_rate_variance_bps: int,
     *     entities_summary: list<array{entity_code: string, etr_bps: int, efficiency_status: string}>
     * }
     */
    public function calculateConsolidatedEtr(array $entitiesTaxData): array
    {
        $totalProfit = 0;
        $totalTax = 0;
        $summaries = [];

        foreach ($entitiesTaxData as $item) {
            $profit = (int) $item['pre_tax_profit_idr'];
            $tax = (int) $item['tax_expense_idr'];

            $totalProfit += $profit;
            $totalTax += $tax;

            $etr = ($profit > 0) ? (int) intdiv($tax * 10000, $profit) : 0;
            $status = ($etr < 2200) ? 'TAX_INCENTIVE_OPTIMAL' : (($etr === 2200) ? 'STATUTORY_BENCHMARK' : 'EXCESS_NON_DEDUCTIBLE');

            $summaries[] = [
                'entity_code' => $item['entity_code'],
                'etr_bps' => $etr,
                'efficiency_status' => $status,
            ];
        }

        $groupEtr = ($totalProfit > 0) ? (int) intdiv($totalTax * 10000, $totalProfit) : 0;
        $variance = $groupEtr - CorporateFiscalReconciliationEngine::STATUTORY_TAX_RATE_BPS;

        return [
            'total_pre_tax_profit_idr' => $totalProfit,
            'total_tax_expense_idr' => $totalTax,
            'consolidated_etr_bps' => $groupEtr,
            'statutory_tax_rate_bps' => CorporateFiscalReconciliationEngine::STATUTORY_TAX_RATE_BPS,
            'tax_rate_variance_bps' => $variance,
            'entities_summary' => $summaries,
        ];
    }
}
