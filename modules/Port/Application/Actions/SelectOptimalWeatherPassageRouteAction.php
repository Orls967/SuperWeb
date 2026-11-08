<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Modules\Port\Application\Services\OceanFleetWeatherRoutingOptimumRouteService;
use Modules\Port\Domain\Models\FleetVoyage;
use Modules\Port\Domain\Models\FleetWeatherRouting;

/**
 * Action Seleksi Rute Cuaca Optimum & Penerbitan Rencana Navigasi Dinamis.
 */
final class SelectOptimalWeatherPassageRouteAction
{
    public function __construct(
        private readonly OceanFleetWeatherRoutingOptimumRouteService $optimumRouteService
    ) {}

    public function execute(
        int $voyageId,
        float $forecastWaveHeightM,
        float $forecastWaveDirection = 0.0
    ): array {
        $voyage = FleetVoyage::with('vessel')->findOrFail($voyageId);
        $vessel = $voyage->vessel;

        $distance = (float) $voyage->planned_distance_nm;
        $speed = (float) $vessel->design_speed_knots;
        $length = ((float) $vessel->length_overall_cm) / 100.0;

        $evaluation = $this->optimumRouteService->evaluateAlternativeWeatherRoutes(
            $distance,
            $speed,
            $length,
            $forecastWaveHeightM,
            $forecastWaveDirection
        );

        $chosen = $evaluation['recommended_route'];
        $extraDistance = 0.0;
        $fuelSavings = 0.0;

        if ($chosen === 'WEATHER_AVOIDANCE') {
            $extraDistance = $evaluation['weather_avoidance_route']['distance_nm'] - $distance;
            $fuelSavings = $evaluation['direct_route']['total_bunker_mt'] - $evaluation['weather_avoidance_route']['total_bunker_mt'];
        } elseif ($chosen === 'COASTAL_PROTECTED') {
            $extraDistance = $evaluation['coastal_protected_route']['distance_nm'] - $distance;
            $fuelSavings = $evaluation['direct_route']['total_bunker_mt'] - $evaluation['coastal_protected_route']['total_bunker_mt'];
        }

        $routing = FleetWeatherRouting::create([
            'voyage_id' => $voyage->id,
            'advisory_source' => 'IMO_ISOCHRONE_OPTIMUM_SYSTEM',
            'weather_pattern_alert' => "FORECAST_WAVE_HS_{$forecastWaveHeightM}M",
            'significant_wave_height_m' => $forecastWaveHeightM,
            'recommended_speed_reduction_knots' => $evaluation['direct_route']['speed_loss_percentage'] * 0.1,
            'diversion_distance_nm' => max(0.0, $extraDistance),
            'estimated_fuel_saving_mt' => max(0.0, $fuelSavings),
            'route_diversion_accepted' => true,
        ]);

        return [
            'voyage_id' => $voyage->id,
            'weather_routing_id' => $routing->id,
            'selected_profile' => $chosen,
            'evaluation' => $evaluation,
        ];
    }
}
