<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DataModelOpsGovernanceService;
use Tests\TestCase;

class DataModelOpsGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected DataModelOpsGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataModelOpsGovernanceService::class);
    }

    public function test_model_deployment_drift_monitoring_and_rollback_flow(): void
    {
        // 432.1 & 432.2 Deploy model with rollback plan and lineage impact analysis
        $dep = $this->service->proposeDeployment(
            changeCode: 'MOD-CHG-FRAUD-V2',
            modelOrDataset: 'fraud_detection_xgboost',
            version: '2.0.0',
            priorVersion: '1.9.4',
            rollbackPlan: 'Revert Kubernetes model-serving deployment manifest to image tag: 1.9.4 and re-route traffic',
            affectedLineageEntities: ['checkout_pipeline', 'pos_terminal_agent', 'fraud_ops_dashboard']
        );

        $this->assertEquals('MOD-CHG-FRAUD-V2', $dep->change_code);
        $this->assertEquals('deployed', $dep->status);
        $this->assertTrue((bool) $dep->lineage_gate_passed);

        // 432.3 Drift monitoring below threshold
        $driftNormal = $this->service->recordDriftScore('MOD-CHG-FRAUD-V2', 0.12);
        $this->assertFalse((bool) $driftNormal->drift_alert_triggered);

        // Drift spikes beyond threshold (> 0.25)
        $driftSpike = $this->service->recordDriftScore('MOD-CHG-FRAUD-V2', 0.38);
        $this->assertTrue((bool) $driftSpike->drift_alert_triggered);

        // 432.4 Rollback restores prior version
        $rolledBack = $this->service->executeRollback('MOD-CHG-FRAUD-V2');
        $this->assertEquals('rolled_back', $rolledBack->status);
        $this->assertEquals('1.9.4', $rolledBack->version);

        // 432.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_missing_rollback_and_empty_lineage_blocked_edge_cases(): void
    {
        // 432.5 Edge case: Missing rollback plan blocks deployment
        try {
            $this->service->proposeDeployment(
                changeCode: 'MOD-NO-ROLLBACK',
                modelOrDataset: 'pricing_engine',
                version: '3.0',
                priorVersion: '2.0',
                rollbackPlan: '', // Missing!
                affectedLineageEntities: ['pricing_dashboard']
            );
            $this->fail('Expected exception for missing rollback plan');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Mandatory rollback plan is missing', $e->getMessage());
        }

        // 432.6 Risk: Incomplete lineage analysis blocks approval
        try {
            $this->service->proposeDeployment(
                changeCode: 'MOD-NO-LINEAGE',
                modelOrDataset: 'pricing_engine',
                version: '3.0',
                priorVersion: '2.0',
                rollbackPlan: 'Valid revert command to v2.0',
                affectedLineageEntities: [] // Empty!
            );
            $this->fail('Expected exception for empty lineage analysis');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Lineage-based impact analysis must document affected downstream entities', $e->getMessage());
        }
    }
}
