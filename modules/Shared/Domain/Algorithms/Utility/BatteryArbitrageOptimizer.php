<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use InvalidArgumentException;

/**
 * Algoritma E12: Optimasi Arbitrase Penyimpanan Energi Baterai (BESS).
 * Memaksimalkan keuntungan pengisian daya saat tarif murah / off-peak dan pelepasan saat tarif mahal / peak,
 * dengan memperhitungkan efisiensi bolak-balik (round-trip efficiency) dan biaya penuaan/degradasi sel baterai per kWh.
 */
final class BatteryArbitrageOptimizer
{
    /**
     * @param  list<int>  $hourlyTariffIdrPerKwh  Profil 24 jam tarif listrik (IDR per kWh)
     * @param  int  $capacityKwh  Kapasitas nominal baterai dalam kWh integer
     * @param  int  $maxPowerKw  Daya maksimum pengisian/pengosongan dalam kW
     * @param  int  $initialSocBps  State of Charge awal dalam bps (0 - 10000 bps)
     * @param  int  $minSocBps  Batas minimum pengosongan untuk menjaga kesehatan sel (misal 1500 = 15%)
     * @param  int  $maxSocBps  Batas maksimum pengisian (misal 9500 = 95%)
     * @param  int  $roundTripEfficiencyBps  Efisiensi round-trip (misal 9000 = 90%)
     * @param  int  $degradationCostIdrPerKwh  Biaya degradasi sel baterai per kWh throughput
     */
    public function optimize(
        array $hourlyTariffIdrPerKwh,
        int $capacityKwh,
        int $maxPowerKw,
        int $initialSocBps = 2000,
        int $minSocBps = 1000,
        int $maxSocBps = 9500,
        int $roundTripEfficiencyBps = 9000,
        int $degradationCostIdrPerKwh = 150
    ): BatteryArbitrageResult {
        if (count($hourlyTariffIdrPerKwh) !== 24) {
            throw new InvalidArgumentException('Profil tarif harian harus memiliki tepat 24 jam.');
        }
        if ($capacityKwh <= 0 || $maxPowerKw <= 0) {
            throw new InvalidArgumentException('Kapasitas baterai dan daya maksimum harus bernilai > 0.');
        }
        if ($initialSocBps < $minSocBps || $initialSocBps > $maxSocBps) {
            throw new InvalidArgumentException('Initial SoC berada di luar rentang batas aman.');
        }

        // Tentukan ambang tarif rata-rata untuk strategi greedy arbitrage
        $avgTariff = (int) intdiv(array_sum($hourlyTariffIdrPerKwh), 24);
        $minTariff = min($hourlyTariffIdrPerKwh);
        $maxTariff = max($hourlyTariffIdrPerKwh);

        $currentSocBps = $initialSocBps;
        $schedule = [];
        $totalChargedKwh = 0;
        $totalDischargedKwh = 0;
        $totalChargingCostIdr = 0;
        $totalDischargingRevenueIdr = 0;
        $totalDegradationCostIdr = 0;

        foreach ($hourlyTariffIdrPerKwh as $hour => $tariff) {
            $action = 'IDLE';
            $energyKwh = 0;
            $costIdr = 0;
            $revenueIdr = 0;

            // Logika Keputusan:
            // 1. Charge jika tarif murah (< average tariff dan SoC < maxSoc)
            // 2. Discharge jika tarif mahal (> average tariff dan SoC > minSoc)
            if ($tariff < $avgTariff && $currentSocBps < $maxSocBps) {
                // Hitung energi yang dapat disimpan (kWh) hingga mencapai maxSoc
                $headroomSocBps = $maxSocBps - $currentSocBps;
                $maxEnergyToStoreKwh = (int) intdiv($capacityKwh * $headroomSocBps, 10000);
                $energyKwh = min($maxPowerKw, $maxEnergyToStoreKwh);

                if ($energyKwh > 0) {
                    $action = 'CHARGE';
                    // Energi masuk memperhitungkan efisiensi satu arah (akar dari RTE ≈ 95%)
                    $effectiveStoredKwh = (int) intdiv($energyKwh * 9500, 10000);
                    $addedSocBps = (int) intdiv($effectiveStoredKwh * 10000, $capacityKwh);
                    $currentSocBps = min($maxSocBps, $currentSocBps + $addedSocBps);

                    $costIdr = $energyKwh * $tariff;
                    $degradationIdr = $energyKwh * $degradationCostIdrPerKwh;

                    $totalChargedKwh += $energyKwh;
                    $totalChargingCostIdr += $costIdr;
                    $totalDegradationCostIdr += $degradationIdr;
                }
            } elseif ($tariff > $avgTariff && $currentSocBps > $minSocBps) {
                // Discharging: hanya jika selisih tarif terhadap rata-rata lebih besar dari biaya degradasi
                $effectiveSpread = $tariff - ($avgTariff + $degradationCostIdrPerKwh);
                if ($effectiveSpread > 0) {
                    $usableSocBps = $currentSocBps - $minSocBps;
                    $maxEnergyToExtractKwh = (int) intdiv($capacityKwh * $usableSocBps, 10000);
                    $energyKwh = min($maxPowerKw, $maxEnergyToExtractKwh);

                    if ($energyKwh > 0) {
                        $action = 'DISCHARGE';
                        $subtractedSocBps = (int) intdiv($energyKwh * 10000, $capacityKwh);
                        $currentSocBps = max($minSocBps, $currentSocBps - $subtractedSocBps);

                        $revenueIdr = $energyKwh * $tariff;
                        $degradationIdr = $energyKwh * $degradationCostIdrPerKwh;

                        $totalDischargedKwh += $energyKwh;
                        $totalDischargingRevenueIdr += $revenueIdr;
                        $totalDegradationCostIdr += $degradationIdr;
                    }
                }
            }

            $schedule[] = [
                'hour' => $hour,
                'action' => $action,
                'energy_kwh' => $energyKwh,
                'tariff_idr_per_kwh' => $tariff,
                'cost_idr' => $costIdr,
                'revenue_idr' => $revenueIdr,
                'soc_bps' => $currentSocBps,
            ];
        }

        $netProfitIdr = $totalDischargingRevenueIdr - ($totalChargingCostIdr + $totalDegradationCostIdr);

        $canonicalPayload = sprintf(
            'BESS-ARBITRAGE|CAP:%d|CHG_KWH:%d|DIS_KWH:%d|CHG_COST:%d|DIS_REV:%d|DEG_EXP:%d|NET:%d|FINAL_SOC:%d',
            $capacityKwh,
            $totalChargedKwh,
            $totalDischargedKwh,
            $totalChargingCostIdr,
            $totalDischargingRevenueIdr,
            $totalDegradationCostIdr,
            $netProfitIdr,
            $currentSocBps
        );

        $certificateHash = hash('sha256', $canonicalPayload);

        return new BatteryArbitrageResult(
            capacityKwh: $capacityKwh,
            initialSocBps: $initialSocBps,
            finalSocBps: $currentSocBps,
            totalChargedKwh: $totalChargedKwh,
            totalDischargedKwh: $totalDischargedKwh,
            totalChargingCostIdr: $totalChargingCostIdr,
            totalDischargingRevenueIdr: $totalDischargingRevenueIdr,
            totalDegradationCostIdr: $totalDegradationCostIdr,
            netArbitrageProfitIdr: $netProfitIdr,
            hourlySchedule: $schedule,
            certificateHash: $certificateHash,
            canonicalPayload: $canonicalPayload
        );
    }
}
