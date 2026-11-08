<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Port;

/**
 * A10: Bunker Fuel Consumption Optimizer & SFOC Engine.
 *
 * Standar: ISO 8217:2017 (Marine Fuel Specifications) & Admiralty Coefficient Method.
 * Menghitung konsumsi bahan bakar maritim berdasarkan hukum kubik kecepatan,
 * karakteristik mesin diesel 2-tak / 4-tak, kurva Specific Fuel Oil Consumption (SFOC),
 * margin cuaca Beaufort/Douglas, dan optimasi biaya bunkering antar-pelabuhan.
 */
final class BunkerFuelConsumptionOptimizer
{
    // Nilai kalor standar ISO 8217 (MJ/kg)
    public const LHV_VLSFO_BASE_MJ_KG = 41.20;

    public const LHV_LSMGO_BASE_MJ_KG = 42.70;

    public const DENSITY_VLSFO_15C_KG_M3 = 991.0;

    public const DENSITY_LSMGO_15C_KG_M3 = 860.0;

    /**
     * Hitung estimasi konsumsi bahan bakar harian (MT/hari) pada kecepatan dan sarat kapal tertentu.
     *
     * @param  float  $displacementTons  Bobot benaman (displacement) kapal dalam metrik ton
     * @param  float  $speedKnots  Kecepatan kapal yang diminta (knots)
     * @param  float  $designSpeedKnots  Kecepatan desain kapal pada MCR (knots)
     * @param  float  $designPowerKw  Daya mesin utama desain (kW)
     * @param  float  $sfocBaseGramsKwh  SFOC desain pada beban optimal (g/kWh)
     * @param  float  $auxiliaryLoadKw  Beban rata-rata mesin bantu/genset (kW)
     * @param  float  $auxiliarySfoc  SFOC mesin bantu (g/kWh)
     * @param  int  $beaufortScale  Skala angin Beaufort (0-12)
     * @param  string  $fuelType  VLSFO atau LSMGO
     * @return array{
     *     speed_knots: float,
     *     engine_power_kw: float,
     *     engine_load_percentage: float,
     *     corrected_sfoc_g_kwh: float,
     *     weather_margin_factor: float,
     *     main_engine_consumption_mt_day: float,
     *     auxiliary_consumption_mt_day: float,
     *     total_daily_consumption_mt: float
     * }
     */
    public function calculateDailyConsumption(
        float $displacementTons,
        float $speedKnots,
        float $designSpeedKnots,
        float $designPowerKw,
        float $sfocBaseGramsKwh = 175.0,
        float $auxiliaryLoadKw = 650.0,
        float $auxiliarySfoc = 210.0,
        int $beaufortScale = 3,
        string $fuelType = 'VLSFO'
    ): array {
        $speed = max(5.0, min($speedKnots, $designSpeedKnots * 1.15));
        $designSpeed = max(8.0, $designSpeedKnots);
        $designPower = max(500.0, $designPowerKw);

        // Hukum Propulsi Kubik: P / P_design = (V / V_design)^3 * (Displacement / Displacement_design)^(2/3)
        $speedRatio = $speed / $designSpeed;
        $powerRequired = $designPower * ($speedRatio ** 3.0);

        // Faktor margin cuaca berdasarkan skala Beaufort (IMO MSC.1/Circ.1501)
        $weatherFactor = 1.0;
        if ($beaufortScale >= 4) {
            $weatherFactor = 1.0 + (($beaufortScale - 3) * 0.045);
        }
        $actualPower = min($designPower * 1.10, $powerRequired * $weatherFactor);
        $engineLoadPct = ($actualPower / $designPower) * 100.0;

        // Kurva SFOC terhadap beban mesin:
        // Beban optimal adalah 75%-85% MCR.
        // Pada beban < 50% (slow steaming ekstrem), SFOC naik karena efisiensi termal turbocharger turun.
        // Pada beban > 90%, SFOC juga naik karena batasan rasio udara-bahan bakar.
        $sfocMultiplier = 1.0;
        if ($engineLoadPct < 50.0) {
            $sfocMultiplier = 1.0 + (0.12 * ((50.0 - $engineLoadPct) / 50.0));
        } elseif ($engineLoadPct < 75.0) {
            $sfocMultiplier = 1.0 + (0.04 * ((75.0 - $engineLoadPct) / 25.0));
        } elseif ($engineLoadPct > 85.0) {
            $sfocMultiplier = 1.0 + (0.05 * (($engineLoadPct - 85.0) / 15.0));
        }

        $correctedSfoc = $sfocBaseGramsKwh * $sfocMultiplier;

        // Konsumsi Mesin Utama: (Power [kW] * SFOC [g/kWh] * 24 h) / 1,000,000 g/MT
        $meDailyMt = ($actualPower * $correctedSfoc * 24.0) / 1_000_000.0;

        // Konsumsi Mesin Bantu (Genset):
        $aeDailyMt = ($auxiliaryLoadKw * $auxiliarySfoc * 24.0) / 1_000_000.0;

        $totalDailyMt = round($meDailyMt + $aeDailyMt, 4);

        return [
            'speed_knots' => round($speed, 2),
            'engine_power_kw' => round($actualPower, 2),
            'engine_load_percentage' => round($engineLoadPct, 2),
            'corrected_sfoc_g_kwh' => round($correctedSfoc, 2),
            'weather_margin_factor' => round($weatherFactor, 4),
            'main_engine_consumption_mt_day' => round($meDailyMt, 4),
            'auxiliary_consumption_mt_day' => round($aeDailyMt, 4),
            'total_daily_consumption_mt' => $totalDailyMt,
        ];
    }

    /**
     * Hitung total konsumsi perjalanan (voyage total consumption) untuk jarak dan kecepatan tertentu.
     */
    public function calculateVoyageBunker(
        float $distanceNm,
        float $speedKnots,
        float $displacementTons,
        float $designSpeedKnots,
        float $designPowerKw,
        int $seaMarginPercentage = 5,
        float $portDaysAtLoad = 2.0,
        float $portDaysAtDisch = 2.5,
        float $portDailyBoilerAndAuxMt = 3.5
    ): array {
        $speed = max(5.0, $speedKnots);
        $steamingHours = $distanceNm / $speed;
        $steamingDays = $steamingHours / 24.0;

        $dailySea = $this->calculateDailyConsumption(
            $displacementTons,
            $speed,
            $designSpeedKnots,
            $designPowerKw
        );

        $seaMarginFactor = 1.0 + ($seaMarginPercentage / 100.0);
        $totalSeaBunkerMt = $dailySea['total_daily_consumption_mt'] * $steamingDays * $seaMarginFactor;

        $totalPortDays = $portDaysAtLoad + $portDaysAtDisch;
        $totalPortBunkerMt = $totalPortDays * $portDailyBoilerAndAuxMt;

        $totalVoyageBunkerMt = round($totalSeaBunkerMt + $totalPortBunkerMt, 2);

        return [
            'distance_nm' => round($distanceNm, 2),
            'speed_knots' => round($speed, 2),
            'steaming_hours' => round($steamingHours, 2),
            'steaming_days' => round($steamingDays, 2),
            'sea_bunker_mt' => round($totalSeaBunkerMt, 2),
            'port_bunker_mt' => round($totalPortBunkerMt, 2),
            'total_voyage_bunker_mt' => $totalVoyageBunkerMt,
            'recommended_safety_reserve_mt' => round($totalVoyageBunkerMt * 0.10, 2),
        ];
    }

    /**
     * Evaluasi pemilihan pelabuhan bunkering berbiaya termurah (Bunkering Port Optimizer).
     * Membandingkan harga per ton, biaya deviasi rute, dan port dues bunker call.
     *
     * @param array<int, array{
     *     port_code: string,
     *     port_name: string,
     *     vlsfo_price_usd_mt: float,
     *     deviation_distance_nm: float,
     *     calling_cost_usd: float
     * }> $portCandidates
     * @return array{
     *     optimal_port_code: string,
     *     optimal_port_name: string,
     *     comparisons: array<int, array{
     *         port_code: string,
     *         fuel_cost_usd: float,
     *         deviation_cost_usd: float,
     *         calling_cost_usd: float,
     *         total_delivered_cost_usd: float,
     *         effective_price_per_mt: float
     *     }>
     * }
     */
    public function optimizeBunkeringPort(
        array $portCandidates,
        float $bunkerRequirementMt,
        float $dailyCharterRateUsd = 18000.0,
        float $transitSpeedKnots = 13.0,
        float $dailyConsumptionMt = 28.0
    ): array {
        $comparisons = [];
        $cheapestPortCode = '';
        $lowestTotalCost = PHP_FLOAT_MAX;
        $cheapestPortName = '';

        foreach ($portCandidates as $candidate) {
            $fuelCost = $bunkerRequirementMt * $candidate['vlsfo_price_usd_mt'];

            // Biaya deviasi: waktu deviasi bolak-balik (extra hours) * (sewa harian + konsumsi harian bahan bakar)
            $extraDistance = $candidate['deviation_distance_nm'];
            $extraDays = $extraDistance > 0 ? ($extraDistance / ($transitSpeedKnots * 24.0)) : 0.0;
            $fuelPrice = $candidate['vlsfo_price_usd_mt'];
            $deviationCost = $extraDays * ($dailyCharterRateUsd + ($dailyConsumptionMt * $fuelPrice));

            $totalCost = $fuelCost + $deviationCost + $candidate['calling_cost_usd'];
            $effectivePrice = $totalCost / max(1.0, $bunkerRequirementMt);

            $comparisons[] = [
                'port_code' => $candidate['port_code'],
                'fuel_cost_usd' => round($fuelCost, 2),
                'deviation_cost_usd' => round($deviationCost, 2),
                'calling_cost_usd' => round($candidate['calling_cost_usd'], 2),
                'total_delivered_cost_usd' => round($totalCost, 2),
                'effective_price_per_mt' => round($effectivePrice, 2),
            ];

            if ($totalCost < $lowestTotalCost) {
                $lowestTotalCost = $totalCost;
                $cheapestPortCode = $candidate['port_code'];
                $cheapestPortName = $candidate['port_name'];
            }
        }

        return [
            'optimal_port_code' => $cheapestPortCode,
            'optimal_port_name' => $cheapestPortName,
            'comparisons' => $comparisons,
        ];
    }
}
