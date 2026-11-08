<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

/**
 * Service Pemilihan Rute Cuaca Optimum Kapal Samudera (Isochrone Method & Speed Loss Analysis).
 * Standar: IMO Circular MSC.1/Circ.1501 & Aertssen's Maritime Speed Loss Formula.
 */
final class OceanFleetWeatherRoutingOptimumRouteService
{
    /**
     * Evaluasi 3 rute alternatif (Great Circle murni vs Deviasi Cuaca vs Jalur Terlindung Pesisir)
     * berdasarkan prakiraan tinggi gelombang signifikan (Significant Wave Height / Hs) dan arah angin.
     *
     * @param  float  $directDistanceNm  Jarak rute langsung (mil laut)
     * @param  float  $vesselDesignSpeedKnots  Kecepatan desain kapal
     * @param  float  $vesselLengthM  Panjang kapal (LOA)
     * @param  float  $stormWaveHeightM  Tinggi gelombang pada rute langsung (meter)
     * @param  float  $stormWaveDirectionDegrees  Arah datang gelombang relatif terhadap haluan kapal
     * @param  float  $fuelPriceUsdPerMt  Harga bahan bakar bunker
     * @param  float  $dailyCharterRateUsd  Tarif sewa kapal harian
     * @return array{
     *     recommended_route: string,
     *     direct_route: array,
     *     weather_avoidance_route: array,
     *     coastal_protected_route: array,
     *     reasoning: string
     * }
     */
    public function evaluateAlternativeWeatherRoutes(
        float $directDistanceNm,
        float $vesselDesignSpeedKnots,
        float $vesselLengthM,
        float $stormWaveHeightM,
        float $stormWaveDirectionDegrees = 0.0, // 0 = head seas (ombak dari depan)
        float $fuelPriceUsdPerMt = 650.0,
        float $dailyCharterRateUsd = 18000.0
    ): array {
        // Formula Penurunan Kecepatan Aertssen (Head Seas Speed Loss):
        // Delta V (%) = (C_weather * (Hs / Length)^0.5) * 100
        $headSeaFactor = abs(cos(deg2rad($stormWaveDirectionDegrees)));
        $waveLengthRatio = $stormWaveHeightM / max(50.0, $vesselLengthM);
        $speedLossPercentage = min(50.0, (18.5 * sqrt($waveLengthRatio)) * $headSeaFactor);

        // 1. Rute Langsung (Direct / Great Circle melalui pusat cuaca buruk):
        $directActualSpeed = max(6.0, $vesselDesignSpeedKnots * (1.0 - ($speedLossPercentage / 100.0)));
        $directSteamingHours = $directDistanceNm / $directActualSpeed;
        $directDailyBunkerMt = 32.0 * (1.0 + ($stormWaveHeightM * 0.08)); // Mesin bekerja lebih berat melawan ombak
        $directFuelMt = ($directSteamingHours / 24.0) * $directDailyBunkerMt;
        $directCost = (($directSteamingHours / 24.0) * $dailyCharterRateUsd) + ($directFuelMt * $fuelPriceUsdPerMt);

        // 2. Rute Deviasi Menghindari Cuaca (Weather Avoidance Route):
        // Menambah jarak 8%-12% tetapi menghindari gelombang tinggi (gelombang turun ke 2.0m)
        $diversionDistanceNm = $directDistanceNm * 1.10;
        $avoidanceSpeedLoss = min(50.0, (18.5 * sqrt(2.0 / max(50.0, $vesselLengthM))));
        $avoidanceSpeed = max(8.0, $vesselDesignSpeedKnots * (1.0 - ($avoidanceSpeedLoss / 100.0)));
        $avoidanceSteamingHours = $diversionDistanceNm / $avoidanceSpeed;
        $avoidanceDailyBunkerMt = 28.0;
        $avoidanceFuelMt = ($avoidanceSteamingHours / 24.0) * $avoidanceDailyBunkerMt;
        $avoidanceCost = (($avoidanceSteamingHours / 24.0) * $dailyCharterRateUsd) + ($avoidanceFuelMt * $fuelPriceUsdPerMt);

        // 3. Rute Terlindung Pesisir (Coastal Protected Route):
        // Jarak bertambah 25% tetapi ombak tenang (1.2m), kecepatan maksimal
        $coastalDistanceNm = $directDistanceNm * 1.25;
        $coastalSpeed = $vesselDesignSpeedKnots * 0.98;
        $coastalSteamingHours = $coastalDistanceNm / $coastalSpeed;
        $coastalDailyBunkerMt = 27.0;
        $coastalFuelMt = ($coastalSteamingHours / 24.0) * $coastalDailyBunkerMt;
        $coastalCost = (($coastalSteamingHours / 24.0) * $dailyCharterRateUsd) + ($coastalFuelMt * $fuelPriceUsdPerMt);

        // Tentukan rute rekomendasi: Jika ombak langsung >= 4.0m, rute langsung dilarang demi keselamatan lambung (cargo shift & parametric rolling risk)
        $recommended = 'WEATHER_AVOIDANCE';
        $reasoning = 'Rute deviasi cuaca direkomendasikan untuk menghindari gelombang tinggi, menjaga integritas kargo, dan meminimalkan biaya bahan bakar total.';

        if ($stormWaveHeightM < 3.0) {
            $recommended = 'DIRECT_GREAT_CIRCLE';
            $reasoning = 'Gelombang relatif aman (< 3.0m). Rute langsung tercepat dan paling efisien.';
        } elseif ($avoidanceCost > $coastalCost && $stormWaveHeightM >= 5.0) {
            $recommended = 'COASTAL_PROTECTED';
            $reasoning = 'Kondisi badai ekstrem di laut lepas. Jalur terlindung pesisir dipilih untuk keselamatan mutlak.';
        }

        return [
            'recommended_route' => $recommended,
            'direct_route' => [
                'distance_nm' => round($directDistanceNm, 1),
                'effective_speed_knots' => round($directActualSpeed, 2),
                'speed_loss_percentage' => round($speedLossPercentage, 1),
                'steaming_hours' => round($directSteamingHours, 1),
                'total_bunker_mt' => round($directFuelMt, 2),
                'total_voyage_cost_usd' => round($directCost, 2),
                'hull_stress_risk' => $stormWaveHeightM >= 4.0 ? 'HIGH_RISK_PARAMETRIC_ROLL' : 'ACCEPTABLE',
            ],
            'weather_avoidance_route' => [
                'distance_nm' => round($diversionDistanceNm, 1),
                'effective_speed_knots' => round($avoidanceSpeed, 2),
                'speed_loss_percentage' => round($avoidanceSpeedLoss, 1),
                'steaming_hours' => round($avoidanceSteamingHours, 1),
                'total_bunker_mt' => round($avoidanceFuelMt, 2),
                'total_voyage_cost_usd' => round($avoidanceCost, 2),
                'hull_stress_risk' => 'LOW_SAFE',
            ],
            'coastal_protected_route' => [
                'distance_nm' => round($coastalDistanceNm, 1),
                'effective_speed_knots' => round($coastalSpeed, 2),
                'steaming_hours' => round($coastalSteamingHours, 1),
                'total_bunker_mt' => round($coastalFuelMt, 2),
                'total_voyage_cost_usd' => round($coastalCost, 2),
                'hull_stress_risk' => 'VERY_LOW_CALM',
            ],
            'reasoning' => $reasoning,
        ];
    }
}
