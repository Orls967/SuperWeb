<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AnalyticsModelMonitoringService;
use Tests\TestCase;

class AnalyticsModelMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsModelMonitoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AnalyticsModelMonitoringService::class);
    }

    public function test_model_documentation_gate_and_serving_traffic(): void
    {
        // 1. Model without documentation cannot serve traffic (345.3 & 345.4)
        try {
            $this->service->deployModel(
                modelCode: 'MODEL-FRAUD-V1',
                modelName: 'Transaction Fraud Detection',
                version: '1.0.0',
                hasDoc: false, // No documentation!
                f1Score: 0.92
            );
            $this->fail('Expected exception for undocumented model');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Model cannot serve traffic without complete documentation', $e->getMessage());
        }

        // 2. Documented model serves traffic (345.1 & 345.4)
        $model = $this->service->deployModel(
            modelCode: 'MODEL-FRAUD-V2',
            modelName: 'Transaction Fraud Detection',
            version: '2.0.0',
            hasDoc: true,
            f1Score: 0.94,
            rollbackVersion: '1.0.0'
        );
        $this->assertTrue((bool) $model->has_complete_documentation);
        $this->assertTrue((bool) $model->is_serving_traffic);
    }

    public function test_retraining_degradation_edge_case_and_rollback(): void
    {
        // Deploy initial model
        $this->service->deployModel(
            modelCode: 'MODEL-PRICING-V1',
            modelName: 'Dynamic Pricing',
            version: '1.2.0',
            hasDoc: true,
            f1Score: 0.8800,
            rollbackVersion: '1.1.0'
        );

        // 1. Retrain with degraded performance is rejected (345.5 Edge Case)
        $evalDegraded = $this->service->evaluateRetrainedModel(
            evalCode: 'EVAL-PRICING-DEGRADED',
            modelCode: 'MODEL-PRICING-V1',
            candidateVersion: '1.3.0',
            candidateF1: 0.8200 // 0.82 < 0.88 degraded!
        );
        $this->assertTrue((bool) $evalDegraded->performance_degraded);
        $this->assertFalse((bool) $evalDegraded->release_approved);

        // 2. Rollback to prior version succeeds (345.1 & 345.4)
        $rolledBack = $this->service->rollbackModel('MODEL-PRICING-V1');
        $this->assertEquals('1.1.0', $rolledBack->model_version);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->deployModel('M-AUD', 'Name', '1.0', true, 0.9, '0.9');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: degraded candidate marked approved
        DB::table('analytics_model_retrain_evaluations')->insert([
            'evaluation_code' => 'EVAL-DEFECT-DEGRADED-RELEASE',
            'model_code' => 'M-AUD',
            'candidate_version' => '1.1',
            'candidate_f1_score' => 0.7,
            'performance_degraded' => true,
            'release_approved' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
