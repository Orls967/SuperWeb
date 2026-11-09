<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\ValueObjects\GpsCoordinates;

/**
 * Cold-Chain Fleet Route Optimizer & Thermal Degradation Calculator.
 * Mengoptimalkan rute kurir multi-titik pengantaran sediaan termolabil:
 * 1. Algoritma Nearest-Neighbor dengan batas waktu pengantaran ketat (SLA < 120 menit).
 * 2. Peramalan degradasi es gel / cool-pack pada suhu lingkungan tropis (ambient 32°C).
 * 3. Peringatan dini jika rute perjalanan melampaui ketahanan termal insulasi boks.
 */
class ColdChainFleetRouteOptimizer
{
    private const BASE_COOLANT_ENDURANCE_MINUTES = 180; // 3 jam ketahanan boks pendingin standar

    private const AMBIENT_DEGRADATION_FACTOR_PER_DEGREE = 2.5; // Penurunan menit ketahanan per derajat di atas 25°C

    /**
     * Mengurutkan rute multi-titik pengantaran berdasarkan efisiensi jarak dan jendela waktu.
     *
     * @param  array{lat: float, lon: float}  $depotLocation
     * @param  array<int, array{id: int, lat: float, lon: float, requires_cold_chain: bool}>  $deliveryStops
     * @return array{
     *     ordered_stops: array<int, array{id: int, distance_from_prev_km: float, cumulative_minutes: int}>,
     *     total_distance_km: float,
     *     total_duration_minutes: int,
     *     thermal_endurance_minutes: int,
     *     is_thermal_window_safe: bool
     * }
     */
    public function optimizeDeliverySequence(
        array $depotLocation,
        array $deliveryStops,
        float $ambientTemperatureC = 30.0
    ): array {
        if (empty($deliveryStops)) {
            return [
                'ordered_stops' => [],
                'total_distance_km' => 0.0,
                'total_duration_minutes' => 0,
                'thermal_endurance_minutes' => self::BASE_COOLANT_ENDURANCE_MINUTES,
                'is_thermal_window_safe' => true,
            ];
        }

        // Hitung ketahanan boks pendingin berdasarkan suhu sekitar
        $tempExcess = max(0.0, $ambientTemperatureC - 25.0);
        $thermalEndurance = (int) max(60, self::BASE_COOLANT_ENDURANCE_MINUTES - ($tempExcess * self::AMBIENT_DEGRADATION_FACTOR_PER_DEGREE));

        $unvisited = $deliveryStops;
        $currentCoord = new GpsCoordinates($depotLocation['lat'], $depotLocation['lon']);
        $ordered = [];
        $totalDistance = 0.0;
        $cumulativeTime = 15; // Waktu persiapan awal 15 menit

        while (! empty($unvisited)) {
            $bestIndex = -1;
            $shortestDist = PHP_FLOAT_MAX;

            foreach ($unvisited as $idx => $stop) {
                $stopCoord = new GpsCoordinates($stop['lat'], $stop['lon']);
                $dist = $currentCoord->distanceTo($stopCoord);

                // Prioritaskan paket cold-chain dengan bobot jarak lebih rendah
                $weightedDist = $stop['requires_cold_chain'] ? $dist * 0.8 : $dist;

                if ($weightedDist < $shortestDist) {
                    $shortestDist = $weightedDist;
                    $bestIndex = $idx;
                }
            }

            $chosenStop = $unvisited[$bestIndex];
            $chosenCoord = new GpsCoordinates($chosenStop['lat'], $chosenStop['lon']);
            $legDistance = $currentCoord->distanceTo($chosenCoord);

            $transitMinutes = (int) ceil(($legDistance / 25.0) * 60) + 10; // +10 menit waktu serah terima
            $cumulativeTime += $transitMinutes;
            $totalDistance += $legDistance;

            $ordered[] = [
                'id' => $chosenStop['id'],
                'distance_from_prev_km' => round($legDistance, 2),
                'cumulative_minutes' => $cumulativeTime,
            ];

            $currentCoord = $chosenCoord;
            unset($unvisited[$bestIndex]);
            $unvisited = array_values($unvisited);
        }

        $isSafe = $cumulativeTime <= $thermalEndurance && $cumulativeTime <= 120;

        return [
            'ordered_stops' => $ordered,
            'total_distance_km' => round($totalDistance, 2),
            'total_duration_minutes' => $cumulativeTime,
            'thermal_endurance_minutes' => $thermalEndurance,
            'is_thermal_window_safe' => $isSafe,
        ];
    }
}
