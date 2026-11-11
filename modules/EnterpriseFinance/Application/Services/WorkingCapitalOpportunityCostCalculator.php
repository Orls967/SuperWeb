<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use InvalidArgumentException;

/**
 * Kalkulator Biaya Peluang Modal Kerja Terikat (Opportunity Cost of Capital) (54.5).
 * Menghitung potensi kehilangan pendapatan bunga/imbal hasil investasi likuid
 * atas dana kas yang terperangkap pada piutang dagang dan stok persediaan berlebih.
 */
class WorkingCapitalOpportunityCostCalculator
{
    /**
     * Suku bunga acuan standar Bank Indonesia (BI-Rate: 6.00% / 600 bps).
     */
    public const DEFAULT_ANNUAL_COST_OF_CAPITAL_BPS = 600;

    /**
     * Menghitung beban biaya peluang tahunan atas modal kerja terikat.
     *
     * @param  int  $costOfCapitalBps  Suku bunga acuan modal dalam basis points (default 600 bps = 6.00%)
     * @return array{
     *     entity_code: string,
     *     ar_balance_minor: int,
     *     inventory_balance_minor: int,
     *     total_tied_capital_minor: int,
     *     cost_of_capital_bps: int,
     *     annual_opportunity_cost_minor: int,
     *     monthly_carrying_burden_minor: int
     * }
     */
    public function calculateOpportunityCost(
        string $entityCode,
        int $arBalanceMinor,
        int $inventoryBalanceMinor,
        int $costOfCapitalBps = self::DEFAULT_ANNUAL_COST_OF_CAPITAL_BPS
    ): array {
        if ($arBalanceMinor < 0 || $inventoryBalanceMinor < 0) {
            throw new InvalidArgumentException('Saldo piutang dan persediaan tidak boleh bernilai negatif.');
        }

        if ($costOfCapitalBps <= 0) {
            throw new InvalidArgumentException('Biaya modal (cost of capital) harus bernilai positif.');
        }

        $totalTied = $arBalanceMinor + $inventoryBalanceMinor;

        // Biaya peluang tahunan = Modal Terikat * (Cost of Capital BPS / 10000)
        $annualCost = (int) intdiv($totalTied * $costOfCapitalBps, 10000);

        // Beban bunga modal bulanan
        $monthlyBurden = (int) intdiv($annualCost, 12);

        return [
            'entity_code' => $entityCode,
            'ar_balance_minor' => $arBalanceMinor,
            'inventory_balance_minor' => $inventoryBalanceMinor,
            'total_tied_capital_minor' => $totalTied,
            'cost_of_capital_bps' => $costOfCapitalBps,
            'annual_opportunity_cost_minor' => $annualCost,
            'monthly_carrying_burden_minor' => $monthlyBurden,
        ];
    }
}
