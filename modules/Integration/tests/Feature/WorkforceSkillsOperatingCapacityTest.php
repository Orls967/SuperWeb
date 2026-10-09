<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\WorkforceSkillsOperatingCapacityService;
use Tests\TestCase;

class WorkforceSkillsOperatingCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected WorkforceSkillsOperatingCapacityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkforceSkillsOperatingCapacityService::class);
    }

    public function test_cross_line_qualification_and_rest_rule_enforcement(): void
    {
        // 1. Deployment without qualification fails (379.2 & 379.4)
        try {
            $this->service->deployWorkerCrossLine(
                deploymentCode: 'DEP-MINE-TECH-01',
                workerId: 'WRK-HEAVY-OPS-99',
                targetLine: 'PORT_LOGISTICS',
                qualificationGatePassed: false, // Unqualified!
                restRulesRespected: true
            );
            $this->fail('Expected exception for unqualified worker deployment');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('lacks required qualification certification', $e->getMessage());
        }

        // 2. Deployment violating rest rules fails (379.2, 379.4, 379.6 Risk)
        try {
            $this->service->deployWorkerCrossLine(
                deploymentCode: 'DEP-MINE-TECH-02',
                workerId: 'WRK-HEAVY-OPS-99',
                targetLine: 'PORT_LOGISTICS',
                qualificationGatePassed: true,
                restRulesRespected: false // Overtime/fatigue violation!
            );
            $this->fail('Expected exception for rest rule violation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Mandatory rest period not met', $e->getMessage());
        }

        // 3. Fully qualified & rested worker deployment succeeds (379.2 & 379.4)
        $deployment = $this->service->deployWorkerCrossLine(
            deploymentCode: 'DEP-MINE-TECH-03',
            workerId: 'WRK-HEAVY-OPS-99',
            targetLine: 'PORT_LOGISTICS',
            qualificationGatePassed: true,
            restRulesRespected: true
        );
        $this->assertTrue((bool) $deployment->qualification_gate_passed);
        $this->assertTrue((bool) $deployment->deployment_approved);
    }

    public function test_capacity_reduction_on_shortage_prevents_false_promises_edge_case(): void
    {
        // 5 staff members can support max 50 units (5 * 10). Requesting 80 units triggers shortage reduction (379.3, 379.4, 379.5 Edge Case)
        $promise = $this->service->calibrateCapacityPromise(
            promiseCode: 'PRM-HOSPITAL-BEDS-01',
            siteId: 'SITE-CLINIC-EAST',
            availableStaff: 5,
            requestedCapacityUnits: 80
        );

        $this->assertEquals(50, $promise->promised_capacity_units);
        $this->assertTrue((bool) $promise->capacity_reduced_due_to_shortage);
        $this->assertTrue((bool) $promise->false_promise_prevented);
    }

    public function test_hcm_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->deployWorkerCrossLine('D-AUD', 'W1', 'LOGISTICS', true, true);
        $this->service->calibrateCapacityPromise('P-AUD', 'S1', 10, 50);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: overpromised capacity
        DB::table('global_operating_capacity_promises')->insert([
            'promise_code' => 'P-DEFECT-OVERPROMISED',
            'facility_or_site_id' => 'S1',
            'available_staff_count' => 2, // Max 20 units
            'promised_capacity_units' => 100, // Discrepancy!
            'capacity_reduced_due_to_shortage' => false,
            'false_promise_prevented' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
