<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SimulationSyntheticFactoryService;
use Tests\TestCase;

class SimulationSyntheticFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected SimulationSyntheticFactoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SimulationSyntheticFactoryService::class);
    }

    public function test_synthetic_data_generation_privacy_and_drift_detection(): void
    {
        // 1. Privacy-safe generation without drift (p = 0.50 >= 0.05) (269.1 & 269.4)
        $cleanData = $this->service->generateSyntheticDataset(
            datasetCode: 'SYN-MINE-SENSORS-01',
            domainLine: 'MINING',
            seedValue: 12345,
            recordCount: 10000,
            simulatedDriftPValue: 0.50
        );

        $this->assertTrue((bool) $cleanData->privacy_check_passed);
        $this->assertLessThan(0.01, (float) $cleanData->reidentification_risk_score);
        $this->assertFalse((bool) $cleanData->distribution_drift_detected);

        // 2. Population distribution drift detected (p = 0.012 < 0.05) (269.5 Edge Case)
        $driftData = $this->service->generateSyntheticDataset(
            datasetCode: 'SYN-RETAIL-POS-02',
            domainLine: 'RETAIL',
            seedValue: 54321,
            recordCount: 20000,
            simulatedDriftPValue: 0.0120
        );

        $this->assertTrue((bool) $driftData->distribution_drift_detected);
        $this->assertEquals(0.0120, (float) $driftData->drift_p_value);
    }

    public function test_simulation_marketplace_isolation_and_finops_accounting(): void
    {
        // 1. Normal run isolated in sandbox with FinOps cost recorded (269.2 & 269.7)
        $run = $this->service->executeMarketplaceSimulation(
            simulatorType: 'PORT',
            requestorTeam: 'LOGISTICS_OPS',
            costPerRunUsd: 150.00,
            inputParameters: ['berth_capacity' => 12, 'crane_efficiency' => 0.85]
        );

        $this->assertTrue((bool) $run->is_sandbox_isolated);
        $this->assertFalse((bool) $run->wrote_production_data);
        $this->assertEquals(150.00, (float) $run->cost_per_run_usd);

        // 2. Production write attempt rejected (269.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('strictly forbidden from writing to production');
        $this->service->executeMarketplaceSimulation('MINE', 'OPS', 50.0, [], true);
    }

    public function test_counterfactual_analysis_determinism_and_approval(): void
    {
        // 1. Two runs with same parameters produce identical fingerprints (269.3 & 269.6)
        $run1 = $this->service->runCounterfactualAnalysis(
            scenarioCode: 'CF-PRICE-RUN-1',
            baselinePriceUsd: 100.0,
            priceDeltaPct: 10.0,
            seedValue: 20261008
        );

        $run2 = $this->service->runCounterfactualAnalysis(
            scenarioCode: 'CF-PRICE-RUN-2', // identical parameters and seed
            baselinePriceUsd: 100.0,
            priceDeltaPct: 10.0,
            seedValue: 20261008
        );

        $this->assertEquals($run1->deterministic_fingerprint, $run2->deterministic_fingerprint);
        $this->assertEquals($run1->projected_ebitda_impact_usd, $run2->projected_ebitda_impact_usd);

        // 2. Approve counterfactual recommendation (269.3)
        $approved = $this->service->approveCounterfactual('CF-PRICE-RUN-1');
        $this->assertTrue((bool) $approved->is_approved_for_implementation);
    }

    public function test_simulation_synthetic_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->generateSyntheticDataset('SYN-AUD', 'LINE', 1, 100, 0.40);
        $this->service->executeMarketplaceSimulation('GRID', 'ENERGY', 25.0, []);
        $this->service->runCounterfactualAnalysis('CF-AUD', 50.0, 5.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: simulator run that wrote to production
        DB::table('simulation_marketplace_runs')->insert([
            'run_code' => 'SIM-BREACH-PROD',
            'simulator_type' => 'HEALTH',
            'requestor_team' => 'ROGUE',
            'cost_per_run_usd' => 10.0,
            'is_sandbox_isolated' => false,
            'wrote_production_data' => true, // Discrepancy!
            'results_summary_json' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
