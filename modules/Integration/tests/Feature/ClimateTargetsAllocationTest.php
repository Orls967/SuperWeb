<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ClimateTargetsAllocationService;
use Tests\TestCase;

class ClimateTargetsAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected ClimateTargetsAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClimateTargetsAllocationService::class);
    }

    public function test_climate_target_abatement_curve_and_on_track_progress(): void
    {
        // 442.1 Register 2026 science-aligned interim milestone
        $m = $this->service->registerMilestone(
            code: 'MLS-NETZERO-2026',
            targetYear: '2026',
            baselineMt: 500000.00,
            targetReductionMt: 75000.00
        );

        $this->assertEquals('MLS-NETZERO-2026', $m->milestone_code);

        // 442.2 Register carbon abatement cost curve measure
        $curve = $this->service->registerAbatementMeasure(
            measureCode: 'ABATE-EV-TRANSITION-01',
            title: 'Transition Last-Mile Fleet to Electric Vehicles',
            abatementPotentialMt: 28000.00,
            costPerTonneUsd: 42.50,
            scopeTier: 'scope_1',
            curveVersion: '2.0',
            confidenceLabel: 'HIGH'
        );

        $this->assertEquals('ABATE-EV-TRANSITION-01', $curve->measure_code);

        // 442.3 Record progress that achieves/exceeds target reduction
        $progress = $this->service->recordMilestoneProgress('MLS-NETZERO-2026', 82000.00);
        $this->assertFalse((bool) $progress->is_missed);
        $this->assertFalse((bool) $progress->corrective_action_opened);

        // 442.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_missed_milestone_triggers_automatic_corrective_action_edge_case(): void
    {
        // 442.5 Edge case: Missed milestone triggers automatic corrective action ticket without quarterly delay
        $this->service->registerMilestone('MLS-SOLAR-2026', '2026', 100000.00, 20000.00);

        // Achieved only 12,000 MT reduction (< 20,000 MT target)
        $missed = $this->service->recordMilestoneProgress('MLS-SOLAR-2026', 12000.00);

        $this->assertTrue((bool) $missed->is_missed);
        $this->assertTrue((bool) $missed->corrective_action_opened);
        $this->assertNotNull($missed->corrective_action_ticket);
        $this->assertStringStartsWith('CAP-ESG-', $missed->corrective_action_ticket);

        // Audit verifies action is opened
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }
}
