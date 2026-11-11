<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Forecasting;

use InvalidArgumentException;

/**
 * Peramal Ketahanan Kas Korporat (Cash Runway Forecaster) & Analisis Siklus Konversi Kas (Cash Conversion Cycle).
 * Seluruh kalkulasi beroperasi pada bilangan bulat minor unit (sen) tanpa tipe data float (KONSEP §A3).
 */
final class CorporateCashRunwayForecaster
{
    /**
     * Menghitung Metrik Siklus Modal Kerja (Working Capital Metrics):
     *
     * 1. DSO (Days Sales Outstanding) = (Piutang Usaha / Penjualan Bruto) * 365
     * 2. DIO (Days Inventory Outstanding) = (Persediaan Barang / HPP) * 365
     * 3. DPO (Days Payable Outstanding) = (Utang Usaha / Pembelian HPP) * 365
     * 4. CCC (Cash Conversion Cycle) = DIO + DSO - DPO
     *
     * @param int $receivablesMinor Saldo piutang usaha rata-rata.
     * @param int $inventoryMinor Saldo persediaan barang rata-rata.
     * @param int $payablesMinor Saldo utang usaha rata-rata.
     * @param int $annualRevenueMinor Total pendapatan tahunan.
     * @param int $annualCogsMinor Total beban pokok penjualan tahunan.
     * @return array{
     *     dso_days: int,
     *     dio_days: int,
     *     dpo_days: int,
     *     ccc_days: int,
     *     working_capital_efficiency: string
     * }
     */
    public static function calculateCashConversionCycle(
        int $receivablesMinor,
        int $inventoryMinor,
        int $payablesMinor,
        int $annualRevenueMinor,
        int $annualCogsMinor
    ): array {
        if ($annualRevenueMinor <= 0 || $annualCogsMinor <= 0) {
            throw new InvalidArgumentException('Pendapatan dan HPP tahunan harus lebih besar dari nol.');
        }

        $dso = intdiv($receivablesMinor * 365, $annualRevenueMinor);
        $dio = intdiv($inventoryMinor * 365, $annualCogsMinor);
        $dpo = intdiv($payablesMinor * 365, $annualCogsMinor);
        $ccc = $dio + $dso - $dpo;

        $efficiency = match (true) {
            $ccc <= 30 => 'EXCELLENT',
            $ccc <= 60 => 'HEALTHY',
            $ccc <= 90 => 'MODERATE',
            default => 'INEFFICIENT_TRAPPED_CASH',
        };

        return [
            'dso_days' => $dso,
            'dio_days' => $dio,
            'dpo_days' => $dpo,
            'ccc_days' => $ccc,
            'working_capital_efficiency' => $efficiency,
        ];
    }

    /**
     * Mensimulasikan Ketahanan Kas Korporat (Cash Runway) di Bawah 3 Skenario Stres Makroekonomi.
     *
     * @param int $currentCashMinor Saldo kas dan setara kas saat ini.
     * @param int $monthlyBaselineOpexMinor Rata-rata beban operasional bulanan (OPEX).
     * @param int $monthlyBaselineRevenueMinor Rata-rata pendapatan operasional bulanan.
     * @return array{
     *     base_case_runway_months: int,
     *     mild_stress_runway_months: int,
     *     severe_shock_runway_months: int,
     *     monthly_net_burn_base_minor: int,
     *     monthly_net_burn_severe_minor: int,
     *     recommended_liquidity_cushion_minor: int
     * }
     */
    public static function simulateRunwayStressScenarios(
        int $currentCashMinor,
        int $monthlyBaselineOpexMinor,
        int $monthlyBaselineRevenueMinor
    ): array {
        if ($currentCashMinor <= 0 || $monthlyBaselineOpexMinor <= 0) {
            throw new InvalidArgumentException('Saldo kas dan OPEX bulanan harus lebih besar dari nol.');
        }

        // 1. Skenario Base Case: Pendapatan normal, OPEX normal
        $netBurnBase = max(0, $monthlyBaselineOpexMinor - $monthlyBaselineRevenueMinor);
        $runwayBase = ($netBurnBase > 0) ? intdiv($currentCashMinor, $netBurnBase) : 999;

        // 2. Skenario Mild Stress: Pendapatan turun 25%, OPEX tetap
        $mildRevenue = intdiv($monthlyBaselineRevenueMinor * 75, 100);
        $netBurnMild = max(1, $monthlyBaselineOpexMinor - $mildRevenue);
        $runwayMild = intdiv($currentCashMinor, $netBurnMild);

        // 3. Skenario Severe Shock: Pendapatan anjlok 60%, OPEX tertekan inflasi naik 10%
        $shockRevenue = intdiv($monthlyBaselineRevenueMinor * 40, 100);
        $shockOpex = intdiv($monthlyBaselineOpexMinor * 110, 100);
        $netBurnSevere = max(1, $shockOpex - $shockRevenue);
        $runwaySevere = intdiv($currentCashMinor, $netBurnSevere);

        // Rekomendasi bantalan likuiditas minimal 6 bulan burn terburuk
        $recommendedCushion = $netBurnSevere * 6;

        return [
            'base_case_runway_months' => $runwayBase,
            'mild_stress_runway_months' => $runwayMild,
            'severe_shock_runway_months' => $runwaySevere,
            'monthly_net_burn_base_minor' => $netBurnBase,
            'monthly_net_burn_severe_minor' => $netBurnSevere,
            'recommended_liquidity_cushion_minor' => $recommendedCushion,
        ];
    }
}

