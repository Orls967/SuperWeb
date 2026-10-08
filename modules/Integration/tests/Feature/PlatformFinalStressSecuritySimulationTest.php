<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PlatformFinalStressSecuritySimulationService;
use Tests\TestCase;

class PlatformFinalStressSecuritySimulationTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformFinalStressSecuritySimulationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlatformFinalStressSecuritySimulationService::class);
    }

    public function test_stress_simulation_enforces_p99_budget_and_dataset_cleanup(): void
    {
        // 1. Stress test with p99 within 250ms budget succeeds (298.2 & 298.5)
        $sim = $this->service->recordStressSimulation(
            simulationCode: 'STRESS-10K-BOOKINGS-01',
            concurrentRequests: 10000,
            p95LatencyMs: 82.0,
            p99LatencyMs: 145.0,
            maxP99BudgetMs: 250.0
        );
        $this->assertTrue((bool) $sim->p99_budget_enforced);

        // 2. Stress dataset cleaned up post-test (298.7 Edge Case)
        $cleaned = $this->service->cleanupStressDataset('STRESS-10K-BOOKINGS-01');
        $this->assertTrue((bool) $cleaned->stress_dataset_cleaned);

        // 3. Stress test exceeding p99 budget fails (298.2)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stress budget breach: p99 latency');
        $this->service->recordStressSimulation('STRESS-BREACH', 10000, 200.0, 310.0, 250.0);
    }

    public function test_security_suite_permutations_zero_tolerance_guard(): void
    {
        // 1. 0 critical / 0 high findings passes clearance (298.3 & 298.6)
        $cleanSuite = $this->service->evaluateSecurityPermutations(
            testSuiteCode: 'SEC-PERMUTATIONS-2026-FINAL',
            permutationsTested: 125000,
            idorFuzzCount: 50000,
            criticalFindings: 0,
            highFindings: 0
        );
        $this->assertTrue((bool) $cleanSuite->is_security_cleared);

        // 2. High or critical findings strictly reject security clearance (298.3 & 298.6)
        try {
            $this->service->evaluateSecurityPermutations('SEC-VULN', 10000, 2000, 1, 0);
            $this->fail('Expected exception for critical security vulnerability');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Security clearance denied: Suite detected 1 critical', $e->getMessage());
        }
    }

    public function test_disaster_simulation_reconciles_all_assets(): void
    {
        // Reconcile simulated peak festival + grid outage + hospital surge with $0 discrepancy (298.4 & 298.5)
        $recon = $this->service->reconcileDisasterSimulation(
            scenarioCode: 'DISASTER-COMPOUND-SIM-01',
            disasterName: 'Festival Surge + Power Grid Blackout + Hospital Influx',
            reconciledMoneyUsd: 145000000.0,
            discrepancyUsd: 0.00
        );

        $this->assertTrue((bool) $recon->all_assets_reconciled);
        $this->assertEquals(0.00, (float) $recon->discrepancy_amount_usd);

        // Attempting to finish with discrepancy fails
        try {
            $this->service->reconcileDisasterSimulation('DISASTER-UNBALANCED', 'Shock', 10000.0, 50.00);
            $this->fail('Expected exception for asset discrepancy');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Disaster reconciliation failed: Unbalanced assets detected', $e->getMessage());
        }
    }

    public function test_platform_simulation_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordStressSimulation('STRESS-AUD', 1000, 50.0, 100.0);
        $this->service->cleanupStressDataset('STRESS-AUD');
        $this->service->evaluateSecurityPermutations('SEC-AUD', 100, 50, 0, 0);
        $this->service->reconcileDisasterSimulation('DIS-AUD', 'Name', 1000.0, 0.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: uncleaned stress dataset
        DB::table('platform_stress_simulations')->insert([
            'simulation_code' => 'STRESS-LEAKED-DATA',
            'concurrent_requests_target' => 5000,
            'query_p95_latency_ms' => 60.0,
            'query_p99_latency_ms' => 120.0,
            'p99_budget_enforced' => true,
            'stress_dataset_cleaned' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
