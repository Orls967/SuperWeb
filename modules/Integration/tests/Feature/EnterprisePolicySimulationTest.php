<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterprisePolicySimulationService;
use Tests\TestCase;

class EnterprisePolicySimulationTest extends TestCase
{
    use RefreshDatabase;

    protected EnterprisePolicySimulationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterprisePolicySimulationService::class);
    }

    public function test_policy_simulation_read_only_safety_and_disruption_gate(): void
    {
        // 1. Policy causing data mutation throws exception (336.4)
        try {
            $this->service->runPolicySimulation(
                simulationCode: 'SIM-MUTATING-DEFECT',
                policyName: 'STRICT_KYC_RULE_V1',
                sampleCount: 1000,
                blockedCount: 20,
                mutatesData: true // Mutating!
            );
            $this->fail('Expected exception for mutating simulation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Data safety violation: Policy simulation engine must run read-only', $e->getMessage());
        }

        // 2. Safe policy with low disruption (< 10%) is ready for activation (336.1 & 336.4)
        $safeSim = $this->service->runPolicySimulation(
            simulationCode: 'SIM-MARGIN-GUARD-01',
            policyName: 'MARGIN_CAP_RULE_V2',
            sampleCount: 1000,
            blockedCount: 25, // 2.5% disruption
            mutatesData: false
        );
        $this->assertEquals(2.5, (float) $safeSim->simulated_operational_disruption_pct);
        $this->assertFalse((bool) $safeSim->requires_pre_activation_revision);
        $this->assertTrue((bool) $safeSim->ready_for_activation);

        // 3. Disruptive policy (> 10% blocked) strictly requires pre-activation revision (336.5 Edge Case)
        $disruptiveSim = $this->service->runPolicySimulation(
            simulationCode: 'SIM-DISRUPTIVE-LIMIT',
            policyName: 'CREDIT_LIMIT_FREEZE_V3',
            sampleCount: 1000,
            blockedCount: 180, // 18% disruption!
            mutatesData: false
        );
        $this->assertEquals(18.0, (float) $disruptiveSim->simulated_operational_disruption_pct);
        $this->assertTrue((bool) $disruptiveSim->requires_pre_activation_revision);
        $this->assertFalse((bool) $disruptiveSim->ready_for_activation);
    }

    public function test_policy_regression_suite_and_drift_detection(): void
    {
        // 1. Stable policy without drift passes regression gate (336.2 & 336.4)
        $stable = $this->service->runRegressionSuite('REG-DOA-CHECK', 'DELEGATION_OF_AUTHORITY_RULES', false);
        $this->assertFalse((bool) $stable->behavioral_drift_detected);
        $this->assertTrue((bool) $stable->regression_gate_passed);

        // 2. Policy drift detected mandates change ticket and blocks gate (336.2 & 336.4)
        $drifted = $this->service->runRegressionSuite('REG-PROCUREMENT-DRIFT', 'RFQ_AWARD_RULES', true);
        $this->assertTrue((bool) $drifted->behavioral_drift_detected);
        $this->assertTrue((bool) $drifted->change_ticket_mandated);
        $this->assertFalse((bool) $drifted->regression_gate_passed);
    }

    public function test_policy_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->runPolicySimulation('SIM-AUD', 'POLICY-1', 100, 2, false);
        $this->service->runRegressionSuite('REG-AUD', 'POLICY-1', false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: regression drift passing gate
        DB::table('enterprise_policy_regression_suites')->insert([
            'suite_code' => 'REG-DEFECT-UNBLOCKED',
            'policy_rule_name' => 'POLICY-X',
            'behavioral_drift_detected' => true,
            'change_ticket_mandated' => false,
            'regression_gate_passed' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
