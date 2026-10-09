<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\OperationsExcellenceGovernanceService;
use Tests\TestCase;

class OperationsExcellenceGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected OperationsExcellenceGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OperationsExcellenceGovernanceService::class);
    }

    public function test_initiative_registration_and_finance_benefit_validation(): void
    {
        // 408.1 Register improvement initiative
        $init = $this->service->registerInitiative(
            code: 'OPS-LEAN-001',
            title: 'WMS Automated Wave Picking Optimization',
            owner: 'Head of Logistics Ops',
            baselineMetric: 120.00, // minutes per wave
            targetMetric: 75.00
        );

        $this->assertEquals('OPS-LEAN-001', $init->initiative_code);
        $this->assertEquals('in_progress', $init->status);

        // 408.2 Validate benefit
        $completed = $this->service->validateBenefit(
            code: 'OPS-LEAN-001',
            benefitAmount: 450000000.00, // 450M IDR savings
            financeApproved: true
        );

        $this->assertEquals('completed', $completed->status);
        $this->assertTrue((bool) $completed->finance_approved);

        // 408.3 Record operational deviation
        $dev = $this->service->recordDeviation(
            code: 'OPS-LEAN-001',
            deviationCode: 'DEV-2026-01',
            reason: 'Temporary manual sorting due to conveyor belt sensor recalibration',
            approvedBy: 'Ops VP'
        );

        $this->assertEquals('DEV-2026-01', $dev->deviation_code);

        // 408.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_safety_impact_halts_initiative_edge_case(): void
    {
        $this->service->registerInitiative(
            code: 'OPS-SPEED-002',
            title: 'Line Speed Acceleration',
            owner: 'Plant Manager',
            baselineMetric: 100.00,
            targetMetric: 150.00
        );

        // 408.5 Edge case: Adverse safety/quality impact detected -> halted
        $halted = $this->service->flagSafetyImpact('OPS-SPEED-002', 'High vibration reported risking operator hand safety');
        $this->assertTrue((bool) $halted->is_halted);
        $this->assertTrue((bool) $halted->safety_impact_detected);
        $this->assertEquals('halted', $halted->status);

        // Attempting to validate benefit and complete halted program is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has safety/quality impacts and cannot be approved');

        $this->service->validateBenefit('OPS-SPEED-002', 1000000000.00, true);
    }
}
