<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Insurance\Domain\Models\AgriInsurancePolicy;

final class AgriParametricOracleService
{
    /**
     * Membaca umpan data oracle satelit cuaca & stasiun meteorologi untuk komoditas pertanian.
     *
     * @return array<string, mixed>
     */
    public function fetchWeatherStationFeeds(string $regionCode = 'ID-JB'): array
    {
        $now = CarbonImmutable::now();

        return [
            'region_code' => $regionCode,
            'region_name' => 'Jawa Barat (Sentra Padi & Hortikultura)',
            'updated_at' => $now->toIso8601String(),
            'stations' => [
                [
                    'station_id' => 'WS-KRAWANG-01',
                    'district' => 'Karawang Timur',
                    'rainfall_last_30d_mm' => 385,
                    'normal_average_mm' => 220,
                    'ndvi_vegetation_index' => 0.78,
                    'flood_risk_level' => 'HIGH',
                    'drought_risk_level' => 'LOW',
                ],
                [
                    'station_id' => 'WS-SUBANG-02',
                    'district' => 'Subang Utara',
                    'rainfall_last_30d_mm' => 110,
                    'normal_average_mm' => 210,
                    'ndvi_vegetation_index' => 0.42,
                    'flood_risk_level' => 'LOW',
                    'drought_risk_level' => 'MODERATE',
                ],
                [
                    'station_id' => 'WS-INDRAMAYU-03',
                    'district' => 'Indramayu Pesisir',
                    'rainfall_last_30d_mm' => 45,
                    'normal_average_mm' => 190,
                    'ndvi_vegetation_index' => 0.31,
                    'flood_risk_level' => 'LOW',
                    'drought_risk_level' => 'SEVERE',
                ],
            ],
        ];
    }

    /**
     * Memeriksa seluruh polis asuransi tani aktif terhadap umpan oracle saat ini.
     *
     * @return array<int, array{policy_number: string, commodity: string, index_type: string, threshold: int, current_val: int, is_breached: bool}>
     */
    public function evaluatePolicyTriggers(): array
    {
        $policies = AgriInsurancePolicy::where('status', 'active')->get();
        $evaluations = [];

        // Simulasi pembacaan rata-rata regional
        $currentRainfallMm = 385;
        $currentNdviScore = 31; // 0.31 scaled 100

        foreach ($policies as $p) {
            $currentVal = match ($p->index_type) {
                'RAINFALL' => $currentRainfallMm,
                'DROUGHT', 'NDVI_INDEX' => $currentNdviScore,
                default => 200,
            };

            $isBreached = match ($p->index_type) {
                'DROUGHT', 'NDVI_INDEX' => $currentVal <= $p->trigger_threshold_val,
                default => $currentVal >= $p->trigger_threshold_val,
            };

            $evaluations[] = [
                'policy_number' => $p->policy_number,
                'commodity' => $p->commodity,
                'index_type' => $p->index_type,
                'threshold' => (int) $p->trigger_threshold_val,
                'current_val' => $currentVal,
                'is_breached' => $isBreached,
            ];
        }

        return $evaluations;
    }
}
