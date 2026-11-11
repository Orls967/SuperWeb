<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Actions;

use Modules\EnterpriseFinance\Application\Services\WorkingCapitalBenchmarkEngine;
use Modules\EnterpriseFinance\Domain\DTOs\WorkingCapitalOptimizationResultDTO;
use Modules\EnterpriseFinance\Domain\Models\WorkingCapitalMetric;

/**
 * Aksi Optimalisasi Modal Kerja & Pembebasan Kas Likuid Grup (54.5).
 * Menghitung efisiensi siklus konversi kas (CCC), membandingkan terhadap benchmark industri,
 * dan memproyeksikan pelepasan kas (cash release) yang dapat dioptimalkan treasury.
 */
class OptimizeGroupWorkingCapitalAction
{
    public function __construct(
        protected readonly WorkingCapitalBenchmarkEngine $benchmarkEngine
    ) {}

    public function execute(
        string $entityCode,
        string $industry,
        string $period,
        int $revenueMinor,
        int $cogsMinor,
        int $arBalanceMinor,
        int $inventoryBalanceMinor,
        int $apBalanceMinor
    ): WorkingCapitalOptimizationResultDTO {
        $dailySales = ($revenueMinor > 0) ? (int) intdiv($revenueMinor, 365) : 1;
        $dailyCogs = ($cogsMinor > 0) ? (int) intdiv($cogsMinor, 365) : 1;

        $dso = (int) intdiv($arBalanceMinor, $dailySales);
        $dio = (int) intdiv($inventoryBalanceMinor, $dailyCogs);
        $dpo = (int) intdiv($apBalanceMinor, $dailyCogs);
        $ccc = ($dso + $dio) - $dpo;

        $tier = ($ccc <= 30) ? 'EXCELLENT' : (($ccc <= 60) ? 'NORMAL' : 'NEEDS_IMPROVEMENT');
        $nwc = ($arBalanceMinor + $inventoryBalanceMinor) - $apBalanceMinor;

        $bench = $this->benchmarkEngine->benchmarkEntity($entityCode, $industry, $revenueMinor, $ccc);

        WorkingCapitalMetric::updateOrCreate(
            [
                'entity_code' => $entityCode,
                'period' => $period,
            ],
            [
                'revenue_minor' => $revenueMinor,
                'cogs_minor' => $cogsMinor,
                'ar_balance_minor' => $arBalanceMinor,
                'inventory_balance_minor' => $inventoryBalanceMinor,
                'ap_balance_minor' => $apBalanceMinor,
                'dso_days' => $dso,
                'dio_days' => $dio,
                'dpo_days' => $dpo,
                'ccc_days' => $ccc,
                'efficiency_tier' => $tier,
            ]
        );

        return new WorkingCapitalOptimizationResultDTO(
            entityCode: $entityCode,
            period: $period,
            revenueMinor: $revenueMinor,
            cogsMinor: $cogsMinor,
            netWorkingCapitalMinor: $nwc,
            dsoDays: $dso,
            dioDays: $dio,
            dpoDays: $dpo,
            cccDays: $ccc,
            efficiencyTier: $tier,
            potentialCashReleaseMinor: $bench['potential_cash_unlock_minor']
        );
    }
}
