<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\OrgDesignChangeManagementService;
use Tests\TestCase;

class OrgDesignChangeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected OrgDesignChangeManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrgDesignChangeManagementService::class);
    }

    public function test_org_design_scenario_and_execution_flow(): void
    {
        // 425.1 & 425.2 Create org design scenario
        $sc = $this->service->createScenario(
            scenarioCode: 'ORG-FINTECH-CONSOL-2026',
            title: 'Consolidation of Regional Payment & Lending Operations',
            spanOfControlRatio: 6,
            totalProjectedCost: 450000000.00,
            readinessScore: 88.50,
            continuityCoverage: true
        );

        $this->assertEquals('ORG-FINTECH-CONSOL-2026', $sc->scenario_code);
        $this->assertEquals('draft', $sc->status);

        // 425.3 Record position action (redeployment)
        $pos = $this->service->recordPositionAction(
            scenarioCode: 'ORG-FINTECH-CONSOL-2026',
            positionCode: 'POS-LEAD-OPS-01',
            employeeId: 'EMP-LEAD-88',
            actionType: 'redeploy',
            severanceAmount: 0.00,
            encumbranceCleared: true
        );

        $this->assertEquals('POS-LEAD-OPS-01', $pos->position_code);

        // 425.4 Execute restructuring with passed consultation gate
        $executed = $this->service->executeRestructuring('ORG-FINTECH-CONSOL-2026', true);
        $this->assertEquals('executed', $executed->status);
        $this->assertTrue((bool) $executed->consultation_gate_passed);

        // Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_consultation_gate_and_continuity_coverage_blocked_edge_cases(): void
    {
        // 425.4 Consultation gate failure blocks execution
        $this->service->createScenario(
            scenarioCode: 'ORG-BLOCKED-GATE',
            title: 'Unilateral Reorganization',
            spanOfControlRatio: 8,
            totalProjectedCost: 200000000.00,
            readinessScore: 50.00,
            continuityCoverage: true
        );

        try {
            $this->service->executeRestructuring('ORG-BLOCKED-GATE', false);
            $this->fail('Expected exception for failed consultation gate');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Mandatory employee consultation gate has not passed', $e->getMessage());
        }

        // 425.5 Edge case: Missing operational continuity coverage in critical units blocks execution
        $this->service->createScenario(
            scenarioCode: 'ORG-CRITICAL-FLAW',
            title: 'Hasty Shift Restructure',
            spanOfControlRatio: 5,
            totalProjectedCost: 100000000.00,
            readinessScore: 60.00,
            continuityCoverage: false // Missing continuity!
        );

        try {
            $this->service->executeRestructuring('ORG-CRITICAL-FLAW', true);
            $this->fail('Expected exception for missing continuity coverage');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Operational continuity coverage verification missing', $e->getMessage());
        }
    }
}
