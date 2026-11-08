<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Port\Application\Actions\SelectOptimalWeatherPassageRouteAction;
use Modules\Port\Application\Services\OceanFleetWeatherRoutingOptimumRouteService;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Domain\Models\FleetVoyage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Maritime Optimum Weather Routing Suite', function (): void {
    it('evaluates alternative routes and recommends avoidance when waves exceed 4 meters', function (): void {
        $service = new OceanFleetWeatherRoutingOptimumRouteService();

        // Jarak 1000 mil laut, kecepatan 14 knots, panjang 190m, gelombang badai 4.8m
        $res = $service->evaluateAlternativeWeatherRoutes(
            1000.0,
            14.0,
            190.0,
            4.80,
            0.0
        );

        expect($res['recommended_route'])->toBe('WEATHER_AVOIDANCE')
            ->and($res['direct_route']['hull_stress_risk'])->toBe('HIGH_RISK_PARAMETRIC_ROLL')
            ->and($res['weather_avoidance_route']['hull_stress_risk'])->toBe('LOW_SAFE');
    });

    it('selects direct great circle route when waves are mild below 3 meters', function (): void {
        $service = new OceanFleetWeatherRoutingOptimumRouteService();

        $res = $service->evaluateAlternativeWeatherRoutes(
            1000.0,
            14.0,
            190.0,
            1.80,
            0.0
        );

        expect($res['recommended_route'])->toBe('DIRECT_GREAT_CIRCLE');
    });

    it('executes SelectOptimalWeatherPassageRouteAction and persists accepted weather routing', function (): void {
        $fleetService = app(PortFleetService::class);
        $vessel = $fleetService->registerVessel([
            'imo_number' => '9110099',
            'vessel_name' => 'MV Optimum Test',
            'vessel_type' => 'BULK_CARRIER',
            'deadweight_tonnage' => 50000,
            'gross_tonnage' => 30000,
            'net_tonnage' => 18000,
            'length_overall_cm' => 19000,
            'beam_cm' => 3200,
            'design_draft_cm' => 1200,
            'main_engine_power_kw' => 8500,
            'design_speed_knots' => 14.5,
        ]);

        $voyage = FleetVoyage::create([
            'voyage_code' => 'VOY-OPT-01',
            'vessel_id' => $vessel->id,
            'voyage_number' => '01/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'IDSUB',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Tanjung Perak',
            'planned_start_at' => CarbonImmutable::now(),
            'planned_end_at' => CarbonImmutable::now()->addDays(2),
            'planned_distance_nm' => 800.0,
        ]);

        $action = app(SelectOptimalWeatherPassageRouteAction::class);
        $res = $action->execute((int) $voyage->id, 4.5, 0.0);

        expect($res['selected_profile'])->toBe('WEATHER_AVOIDANCE')
            ->and($res['weather_routing_id'])->not->toBeNull();
    });
});
