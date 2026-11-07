<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

/**
 * Layanan Simulator dan Optimasi Match Factor Armada Tambang (Shovel & Haul Truck).
 *
 * Rumus Match Factor:
 * MF = (N_truk * Waktu_muat) / (N_shovel * Waktu_siklus_truk)
 *
 * Kriteria Evaluasi:
 * - MF = 1.0 : Kesetimbangan sempurna antara loader dan armada truk.
 * - MF < 1.0 : Undertrucked (Shovel banyak menganggur menunggu truk).
 * - MF > 1.0 : Overtrucked (Truk mengantre di shovel, kehilangan produktivitas).
 */
final class MatchFactorOptimizationService
{
    /**
     * @param int $truckCount Jumlah unit truk aktif
     * @param int $shovelCount Jumlah unit shovel aktif
     * @param float $loadingTimeMinutes Waktu muat shovel ke 1 truk (menit)
     * @param float $truckCycleTimeMinutes Total waktu siklus 1 truk (muat, angkut, buang, kembali, antre)
     * @param int $hourlyTruckCostIdr Biaya operasional 1 truk per jam
     * @param int $hourlyShovelCostIdr Biaya operasional 1 shovel per jam
     * @return array{
     *     match_factor: float,
     *     state: string,
     *     shovel_waiting_pct: float,
     *     truck_waiting_pct: float,
     *     recommendation: string,
     *     optimal_truck_count: int,
     *     cost_comparison: array{
     *         current_cost_per_ton_idr: int,
     *         optimal_cost_per_ton_idr: int,
     *         potential_saving_pct: float
     *     }
     * }
     */
    public function simulateAndOptimize(
        int $truckCount,
        int $shovelCount,
        float $loadingTimeMinutes,
        float $truckCycleTimeMinutes,
        int $hourlyTruckCostIdr = 1200000,
        int $hourlyShovelCostIdr = 3500000,
    ): array {
        $numerator = $truckCount * $loadingTimeMinutes;
        $denominator = $shovelCount * $truckCycleTimeMinutes;

        $mf = $denominator > 0 ? round($numerator / $denominator, 3) : 1.0;

        // Persentase waktu tunggu
        $shovelWaitPct = 0.0;
        $truckWaitPct = 0.0;

        if ($mf < 1.0) {
            $state = 'UNDERTRUCKED';
            $shovelWaitPct = round((1.0 - $mf) * 100, 1);
            $rec = "Shovel menganggur {$shovelWaitPct}% dari waktu operasi. Disarankan menambah unit truk angkut ke pit ini.";
        } elseif ($mf > 1.0) {
            $state = 'OVERTRUCKED';
            $truckWaitPct = round((($mf - 1.0) / $mf) * 100, 1);
            $rec = "Truk mengalami antrean {$truckWaitPct}% di area loading. Disarankan mengalihkan sebagian truk ke pit lain.";
        } else {
            $state = 'OPTIMAL_EQUILIBRIUM';
            $rec = 'Alokasi armada shovel dan truk seimbang sempurna (Match Factor = 1.0).';
        }

        // Hitung jumlah truk optimal agar MF = 1.0
        // N_truk_opt = (N_shovel * Waktu_siklus) / Waktu_muat
        $optimalTrucks = (int) round(($shovelCount * $truckCycleTimeMinutes) / max(0.1, $loadingTimeMinutes));

        // Analisis biaya per ton
        $truckPayloadTon = 90.0;
        $tripsPerHourPerTruck = 60.0 / max(1.0, $truckCycleTimeMinutes);
        $currentProductionTon = $truckCount * $tripsPerHourPerTruck * $truckPayloadTon;
        $currentTotalHourlyCost = ($truckCount * $hourlyTruckCostIdr) + ($shovelCount * $hourlyShovelCostIdr);
        $currentCostPerTon = $currentProductionTon > 0 ? (int) round($currentTotalHourlyCost / $currentProductionTon) : 0;

        $optProductionTon = $optimalTrucks * $tripsPerHourPerTruck * $truckPayloadTon;
        $optTotalHourlyCost = ($optimalTrucks * $hourlyTruckCostIdr) + ($shovelCount * $hourlyShovelCostIdr);
        $optCostPerTon = $optProductionTon > 0 ? (int) round($optTotalHourlyCost / $optProductionTon) : 0;

        $savingPct = $currentCostPerTon > $optCostPerTon
            ? round((($currentCostPerTon - $optCostPerTon) / $currentCostPerTon) * 100, 1)
            : 0.0;

        return [
            'match_factor' => $mf,
            'state' => $state,
            'shovel_waiting_pct' => $shovelWaitPct,
            'truck_waiting_pct' => $truckWaitPct,
            'recommendation' => $rec,
            'optimal_truck_count' => $optimalTrucks,
            'cost_comparison' => [
                'current_cost_per_ton_idr' => $currentCostPerTon,
                'optimal_cost_per_ton_idr' => $optCostPerTon,
                'potential_saving_pct' => $savingPct,
            ],
        ];
    }
}
