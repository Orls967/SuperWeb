<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SyntheticDataTrainingGovernanceService;
use Tests\TestCase;

class SyntheticDataTrainingGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected SyntheticDataTrainingGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SyntheticDataTrainingGovernanceService::class);
    }

    public function test_training_dataset_unrecorded_lineage_blocking(): void
    {
        // 1. Dataset without lineage throws exception and is blocked (359.4 & 359.6 Risk)
        try {
            $this->service->catalogTrainingDataset(
                datasetCode: 'DS-NICKEL-ASSAY-V1',
                domainName: 'MINING',
                lineageHash: null // Missing lineage!
            );
            $this->fail('Expected exception for training dataset without lineage');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Dataset without recorded lineage is strictly blocked from training pipeline', $e->getMessage());
        }

        // 2. Dataset with lineage succeeds (359.1 & 359.4)
        $ds = $this->service->catalogTrainingDataset(
            datasetCode: 'DS-NICKEL-ASSAY-V2',
            domainName: 'MINING',
            lineageHash: 'SHA256:d8a2bc4e7f81a9c1e0b5d92...'
        );
        $this->assertTrue((bool) $ds->has_recorded_lineage);
        $this->assertTrue((bool) $ds->permitted_for_training_pipeline);
    }

    public function test_synthetic_data_privacy_failure_and_regeneration_edge_case(): void
    {
        // 1. High re-identification risk fails privacy check and throws exception (359.2, 359.4, 359.5 Edge Case)
        try {
            $this->service->evaluateSyntheticDataPrivacy(
                evalCode: 'EVAL-SYN-HR-EMPLOYEE-01',
                syntheticDatasetCode: 'SYN-HR-SALARY-FIXTURE',
                reidentificationRisk: 0.1250, // 12.5% > 5% threshold!
                maxThreshold: 0.0500,
                regeneratedWithNewSeed: false
            );
            $this->fail('Expected exception for synthetic data privacy check failure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('failed check and must be regenerated', $e->getMessage());
        }

        // Verify rejected record exists
        $rejected = DB::table('synthetic_dataset_privacy_evaluations')->where('evaluation_code', 'EVAL-SYN-HR-EMPLOYEE-01')->first();
        $this->assertNotNull($rejected);
        $this->assertFalse((bool) $rejected->privacy_check_passed);
        $this->assertFalse((bool) $rejected->permitted_for_fixtures);

        // 2. Regenerated synthetic dataset with safe risk score succeeds (359.2 & 359.5)
        $regenerated = $this->service->evaluateSyntheticDataPrivacy(
            evalCode: 'EVAL-SYN-HR-EMPLOYEE-02',
            syntheticDatasetCode: 'SYN-HR-SALARY-FIXTURE-V2',
            reidentificationRisk: 0.0120, // 1.2% <= 5%
            maxThreshold: 0.0500,
            regeneratedWithNewSeed: true
        );
        $this->assertTrue((bool) $regenerated->privacy_check_passed);
        $this->assertTrue((bool) $regenerated->permitted_for_fixtures);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->catalogTrainingDataset('DS-AUD', 'ESG', 'SHA256:112233');
        $this->service->evaluateSyntheticDataPrivacy('E-AUD', 'SYN-1', 0.02, 0.05, false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unlineaged dataset permitted
        DB::table('training_data_catalog_datasets')->insert([
            'dataset_code' => 'DS-DEFECT-UNLINEAGED',
            'domain_name' => 'ESG',
            'lineage_hash' => null,
            'has_recorded_lineage' => false,
            'permitted_for_training_pipeline' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
