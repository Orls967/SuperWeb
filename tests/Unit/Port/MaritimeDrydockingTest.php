<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Port\Application\Actions\PlanSpecialSurveyDockingAction;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Application\Services\VesselDrydockingAndSurveyPlanningService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Maritime Drydocking & Special Survey Suite', function (): void {
    beforeEach(function (): void {
        $fleetService = app(PortFleetService::class);
        $this->vessel = $fleetService->registerVessel([
            'imo_number' => '9119988',
            'vessel_name' => 'MV Docking Pioneer',
            'vessel_type' => 'BULK_CARRIER',
            'fleet_class_code' => 'BKI',
            'deadweight_tonnage' => 52000,
            'gross_tonnage' => 31000,
            'net_tonnage' => 18000,
            'length_overall_cm' => 19000,
            'beam_cm' => 3200,
            'design_draft_cm' => 1200,
            'main_engine_power_kw' => 8500,
        ]);
    });

    it('plans detailed drydock specification and calculates shipyard repair cost', function (): void {
        $service = new VesselDrydockingAndSurveyPlanningService();
        $date = CarbonImmutable::now()->addMonths(2);

        $spec = $service->planDrydockSpecification(
            (int) $this->vessel->id,
            'PT Dok & Perkapalan Surabaya',
            $date,
            18,
            15.0,
            4500.0,
            true
        );

        expect($spec['shipyard'])->toBe('PT Dok & Perkapalan Surabaya')
            ->and($spec['duration_days'])->toBe(18)
            ->and($spec['docking_type'])->toBe('SPECIAL_SURVEY_DRYDOCK')
            ->and($spec['estimated_shipyard_cost_usd'])->toBeGreaterThan(250000.0)
            ->and($spec['work_specifications'])->not->toBeEmpty();
    });

    it('executes PlanSpecialSurveyDockingAction and updates vessel classification survey dates', function (): void {
        $action = app(PlanSpecialSurveyDockingAction::class);
        $date = CarbonImmutable::now()->addMonths(1);

        $res = $action->execute(
            (int) $this->vessel->id,
            'PaxOcean Shipyard Batam',
            $date,
            20,
            25.0
        );

        expect($res['drydock_plan']['shipyard'])->toBe('PaxOcean Shipyard Batam')
            ->and($res['vessel_id'])->toBe($this->vessel->id);

        $this->vessel->refresh();
        expect($this->vessel->classification->last_drydock_survey->toDateString())->toBe($date->toDateString());
    });
});
