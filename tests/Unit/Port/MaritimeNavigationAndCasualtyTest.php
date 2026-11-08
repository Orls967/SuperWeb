<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Port\Application\Actions\GenerateOrthodromicPassageTrackAction;
use Modules\Port\Application\Services\MaritimeCasualtyInvestigationService;
use Modules\Port\Application\Services\OceanFleetRouteOptimizationService;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Domain\Models\FleetVoyage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Maritime Navigation, Great Circle & Casualty Investigation', function (): void {
    it('calculates great circle distance and verifies distance saved over rhumb line', function (): void {
        $navService = new OceanFleetRouteOptimizationService();

        // Rute Tokyo (35.6°N, 139.6°E) ke San Francisco (37.7°N, -122.4°W)
        $route = $navService->calculateGreatCircleRoute(
            35.6762,
            139.6503,
            37.7749,
            -122.4194
        );

        expect($route['great_circle_distance_nm'])->toBeGreaterThan(4400.0)
            ->and($route['great_circle_distance_nm'])->toBeLessThan(4600.0)
            ->and($route['distance_saved_nm'])->toBeGreaterThan(150.0); // Great circle hemat ratusan mil laut
    });

    it('generates intermediate waypoints along orthodromic track', function (): void {
        $navService = new OceanFleetRouteOptimizationService();

        $waypoints = $navService->generateWaypointsAlongTrack(
            -6.10,
            106.85, // Tanjung Priok
            1.28,
            103.85, // Singapore
            5
        );

        expect($waypoints)->toHaveCount(6)
            ->and($waypoints[0]['sequence'])->toBe(1)
            ->and($waypoints[5]['sequence'])->toBe(6);
    });

    it('evaluates ocean current drift vector effect on speed made good', function (): void {
        $navService = new OceanFleetRouteOptimizationService();

        // Kapal melaju 14 knots arah utara (0°), dibantu arus Kuroshio 3 knots arah utara (0°)
        $favorable = $navService->evaluateOceanCurrentVector(14.0, 0.0, 3.0, 0.0);
        expect($favorable['speed_made_good_knots'])->toBe(17.0)
            ->and($favorable['effect'])->toBe('FAVORABLE_CURRENT');

        // Arus melawan dari depan (180°)
        $adverse = $navService->evaluateOceanCurrentVector(14.0, 0.0, 3.0, 180.0);
        expect($adverse['speed_made_good_knots'])->toBe(11.0)
            ->and($adverse['effect'])->toBe('ADVERSE_CURRENT');
    });

    it('classifies very serious marine casualty requiring mandatory inquiry under IMO Casualty Code', function (): void {
        $fleetService = app(PortFleetService::class);
        $vessel = $fleetService->registerVessel([
            'imo_number' => '9334455',
            'vessel_name' => 'MV Casualty Test',
            'vessel_type' => 'PRODUCT_TANKER',
            'deadweight_tonnage' => 45000,
            'gross_tonnage' => 28000,
            'net_tonnage' => 14000,
            'length_overall_cm' => 18000,
            'beam_cm' => 3200,
            'design_draft_cm' => 1200,
            'main_engine_power_kw' => 8000,
        ]);

        $casService = new MaritimeCasualtyInvestigationService();

        $report = $casService->logMarineCasualty(
            (int) $vessel->id,
            'COLLISION',
            'SERIOUS',
            'Selat Singapura dekat Raffles Lighthouse',
            CarbonImmutable::now(),
            3500000.0, // Kerusakan > 2M USD -> memicu Very Serious Casualty
            false,
            true // Polusi tumpahan minyak parah
        );

        expect($report['classification'])->toBe('VERY_SERIOUS')
            ->and($report['mandatory_flag_state_inquiry'])->toBeTrue()
            ->and($report['imo_gisis_reportable'])->toBeTrue();
    });

    it('creates orthodromic passage plan via GenerateOrthodromicPassageTrackAction', function (): void {
        $fleetService = app(PortFleetService::class);
        $vessel = $fleetService->registerVessel([
            'imo_number' => '9221100',
            'vessel_name' => 'MV Track Test',
            'vessel_type' => 'BULK_CARRIER',
            'deadweight_tonnage' => 50000,
            'gross_tonnage' => 30000,
            'net_tonnage' => 18000,
            'length_overall_cm' => 19000,
            'beam_cm' => 3200,
            'design_draft_cm' => 1200,
            'main_engine_power_kw' => 8500,
        ]);

        $voyage = FleetVoyage::create([
            'voyage_code' => 'VOY-ORTHO-01',
            'vessel_id' => $vessel->id,
            'voyage_number' => '01/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'SGSIN',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Singapore',
            'planned_start_at' => CarbonImmutable::now(),
            'planned_end_at' => CarbonImmutable::now()->addDays(2),
        ]);

        $action = app(GenerateOrthodromicPassageTrackAction::class);
        $passage = $action->execute(
            (int) $voyage->id,
            'PASSAGE-GC-01',
            -6.10,
            106.85,
            1.28,
            103.85,
            5
        );

        expect($passage->passage_plan_ref)->toBe('PASSAGE-GC-01')
            ->and($passage->waypoints['track_points'])->toHaveCount(6);
    });
});
