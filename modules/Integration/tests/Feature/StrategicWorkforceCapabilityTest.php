<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\StrategicWorkforceCapabilityService;
use Tests\TestCase;

class StrategicWorkforceCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected StrategicWorkforceCapabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StrategicWorkforceCapabilityService::class);
    }

    public function test_scenario_creation_and_capacity_allocation(): void
    {
        // 1. Create scenario projection (321.2 & 321.4)
        $scenario = $this->service->createScenario(
            scenarioCode: 'SCENARIO-MINING-EXPANSION-2027',
            lineCode: 'LINE_MINING',
            scenarioType: 'GROWTH',
            headcountRequired: 150,
            annualCostUsd: 12000000.0
        );
        $this->assertEquals(150, $scenario->projected_headcount_required);

        // 2. Full allocation for critical role when capacity is sufficient (321.4)
        $crit = $this->service->allocateRoleCapacity(
            allocationCode: 'ALLOC-MINE-ENG-01',
            scenarioCode: 'SCENARIO-MINING-EXPANSION-2027',
            roleCode: 'ROLE_SENIOR_MINING_ENGINEER',
            isCriticalRole: true,
            requestedFte: 10,
            availableCapacityFte: 10
        );
        $this->assertEquals(10, $crit->allocated_fte);
        $this->assertEquals(0, $crit->deferred_fte);
        $this->assertEquals('NOT_DEFERRED', $crit->deferred_plan_status);
    }

    public function test_constrained_capacity_critical_role_priority_and_deferral_rules(): void
    {
        $this->service->createScenario('SCENARIO-LOGISTICS-2027', 'LINE_LOGISTICS', 'AUTOMATION', 80, 5000000.0);

        // 1. Constrained capacity on critical role throws exception (critical roles must be 100% funded) (321.5 Edge Case)
        try {
            $this->service->allocateRoleCapacity(
                allocationCode: 'ALLOC-SAFETY-LEAD',
                scenarioCode: 'SCENARIO-LOGISTICS-2027',
                roleCode: 'ROLE_CRITICAL_SAFETY_DIRECTOR',
                isCriticalRole: true,
                requestedFte: 5,
                availableCapacityFte: 3 // Insufficient!
            );
            $this->fail('Expected exception for constrained critical role');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Critical role capacity breach: Role', $e->getMessage());
        }

        // 2. Constrained capacity on non-critical role without deferral plan throws exception (321.5)
        try {
            $this->service->allocateRoleCapacity(
                allocationCode: 'ALLOC-ADMIN-ASSIST',
                scenarioCode: 'SCENARIO-LOGISTICS-2027',
                roleCode: 'ROLE_ADMIN_ASSISTANT',
                isCriticalRole: false,
                requestedFte: 10,
                availableCapacityFte: 4,
                deferralPlan: null
            );
            $this->fail('Expected exception for unplanned non-critical deferral');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Non-critical role deferral requires formal deferral plan documentation', $e->getMessage());
        }

        // 3. Constrained capacity on non-critical role with deferral plan succeeds (321.5)
        $deferred = $this->service->allocateRoleCapacity(
            allocationCode: 'ALLOC-ADMIN-PLANNED',
            scenarioCode: 'SCENARIO-LOGISTICS-2027',
            roleCode: 'ROLE_ADMIN_ASSISTANT',
            isCriticalRole: false,
            requestedFte: 10,
            availableCapacityFte: 4,
            deferralPlan: 'Deferred to Q3 via shared corporate service automation'
        );
        $this->assertEquals(4, $deferred->allocated_fte);
        $this->assertEquals(6, $deferred->deferred_fte);
        $this->assertEquals('FORMALLY_DEFERRED_WITH_PLAN', $deferred->deferred_plan_status);
    }

    public function test_hcm_workforce_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->createScenario('SCEN-AUD', 'LINE', 'BASE', 10, 1000.0);
        $this->service->allocateRoleCapacity('ALL-AUD', 'SCEN-AUD', 'ROLE-1', false, 5, 5);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: critical role with deferred FTE
        DB::table('strategic_workforce_capacity_allocations')->insert([
            'allocation_code' => 'ALLOC-DEFECT-CRITICAL-DEFERRAL',
            'scenario_code' => 'SCEN-AUD',
            'role_code' => 'ROLE_DEFECT_CHIEF',
            'is_critical_role' => true,
            'requested_fte' => 10,
            'allocated_fte' => 5,
            'deferred_fte' => 5, // Discrepancy!
            'deferred_plan_status' => 'FORMALLY_DEFERRED_WITH_PLAN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
