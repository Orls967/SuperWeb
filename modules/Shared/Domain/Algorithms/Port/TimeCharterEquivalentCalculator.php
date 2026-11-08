<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Port;

/**
 * A13: Time Charter Equivalent (TCE) & Voyage Net Daily Revenue Calculator.
 *
 * Standar: Baltic Exchange Voyage Evaluation & BIMCO Maritime Commercial Standards.
 * Menghitung metrik kinerja finansial pelayaran komparatif (USD/hari bersih),
 * margin kontribusi pelayaran terhadap biaya tetap (OPEX & pembiayaan modal),
 * serta analisis sensitivitas volatilitas harga bunker.
 */
final class TimeCharterEquivalentCalculator
{
    /**
     * Hitung tarif TCE harian bersih dari suatu voyage charter.
     *
     * @param  float  $cargoQuantityMt  Jumlah kargo dalam metrik ton
     * @param  float  $freightRateUsdPerMt  Tarif uang tambang per metrik ton
     * @param  float  $seaSteamingDays  Hari pelayaran di laut
     * @param  float  $portDaysLoad  Hari di pelabuhan muat
     * @param  float  $portDaysDisch  Hari di pelabuhan bongkar
     * @param  float  $bunkerSeaCostUsd  Total biaya bahan bakar di laut
     * @param  float  $bunkerPortCostUsd  Total biaya bahan bakar di pelabuhan
     * @param  float  $portDisbursementsUsd  Total PDA/FDA pelabuhan muat dan bongkar
     * @param  float  $canalDuesUsd  Biaya kanal/selat (jika ada)
     * @param  float  $ballastBonusUsd  Bonus ballast yang dibayarkan charterer (jika ada)
     * @param  float  $demurrageEarnedUsd  Uang demurrage yang diperoleh
     * @param  float  $despatchPaidUsd  Uang despas yang dibayarkan
     * @param  float  $otherVoyageExpensesUsd  Biaya ekstra asuransi/agensi
     * @param  float  $dailyOpexTargetUsd  Biaya operasional harian kapal (crewing, stores, spares, insurance)
     * @return array{
     *     gross_revenue_usd: float,
     *     total_voyage_costs_usd: float,
     *     net_voyage_revenue_usd: float,
     *     total_voyage_days: float,
     *     daily_tce_usd: float,
     *     daily_opex_usd: float,
     *     net_daily_profit_usd: float,
     *     voyage_margin_percentage: float,
     *     bunker_cost_share_percentage: float
     * }
     */
    public function calculateTce(
        float $cargoQuantityMt,
        float $freightRateUsdPerMt,
        float $seaSteamingDays,
        float $portDaysLoad,
        float $portDaysDisch,
        float $bunkerSeaCostUsd,
        float $bunkerPortCostUsd,
        float $portDisbursementsUsd,
        float $canalDuesUsd = 0.0,
        float $ballastBonusUsd = 0.0,
        float $demurrageEarnedUsd = 0.0,
        float $despatchPaidUsd = 0.0,
        float $otherVoyageExpensesUsd = 0.0,
        float $dailyOpexTargetUsd = 6500.0
    ): array {
        // 1. Pendapatan Kotor (Gross Revenue):
        $freightRevenue = $cargoQuantityMt * $freightRateUsdPerMt;
        $grossRevenue = $freightRevenue + $ballastBonusUsd + $demurrageEarnedUsd - $despatchPaidUsd;

        // 2. Biaya Perjalanan (Voyage Costs):
        $totalBunkers = $bunkerSeaCostUsd + $bunkerPortCostUsd;
        $totalVoyageCosts = $totalBunkers + $portDisbursementsUsd + $canalDuesUsd + $otherVoyageExpensesUsd;

        // 3. Pendapatan Bersih Pelayaran (Net Voyage Revenue):
        $netVoyageRevenue = $grossRevenue - $totalVoyageCosts;

        // 4. Total Durasi Pelayaran (Total Voyage Days):
        $totalDays = max(0.5, $seaSteamingDays + $portDaysLoad + $portDaysDisch);

        // 5. Daily TCE Rate:
        $dailyTce = $netVoyageRevenue / $totalDays;

        // 6. Net Profit vs OPEX:
        $netDailyProfit = $dailyTce - $dailyOpexTargetUsd;
        $marginPct = $grossRevenue > 0 ? ($netVoyageRevenue / $grossRevenue) * 100.0 : 0.0;
        $bunkerSharePct = $totalVoyageCosts > 0 ? ($totalBunkers / $totalVoyageCosts) * 100.0 : 0.0;

        return [
            'gross_revenue_usd' => round($grossRevenue, 2),
            'total_voyage_costs_usd' => round($totalVoyageCosts, 2),
            'net_voyage_revenue_usd' => round($netVoyageRevenue, 2),
            'total_voyage_days' => round($totalDays, 2),
            'daily_tce_usd' => round($dailyTce, 2),
            'daily_opex_usd' => round($dailyOpexTargetUsd, 2),
            'net_daily_profit_usd' => round($netDailyProfit, 2),
            'voyage_margin_percentage' => round($marginPct, 2),
            'bunker_cost_share_percentage' => round($bunkerSharePct, 2),
        ];
    }
}
