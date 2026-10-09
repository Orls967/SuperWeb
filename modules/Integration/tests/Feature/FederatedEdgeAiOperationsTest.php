<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\FederatedEdgeAiOperationsService;
use Tests\TestCase;

class FederatedEdgeAiOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected FederatedEdgeAiOperationsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FederatedEdgeAiOperationsService::class);
    }

    public function test_federated_rollout_privacy_gate_and_rollback(): void
    {
        // 1. Rollout without privacy check throws exception (354.2, 354.4, 354.6 Risk)
        try {
            $this->service->deployFleetModel(
                rolloutCode: 'ROLLOUT-HAUL-FLEET-V1',
                deviceGroup: 'MINING_HAUL_FLEET',
                modelVersion: '2.1.0',
                priorVersion: '2.0.0',
                privacyPassed: false // Privacy failed!
            );
            $this->fail('Expected exception for unverified privacy federated rollout');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Federated model cannot be distributed to edge fleet without passing privacy checks', $e->getMessage());
        }

        // 2. Verified rollout succeeds (354.2 & 354.4)
        $rollout = $this->service->deployFleetModel(
            rolloutCode: 'ROLLOUT-HAUL-FLEET-V2',
            deviceGroup: 'MINING_HAUL_FLEET',
            modelVersion: '2.1.0',
            priorVersion: '2.0.0',
            privacyPassed: true
        );
        $this->assertTrue((bool) $rollout->privacy_check_passed);
        $this->assertEquals('2.1.0', $rollout->model_version);

        // 3. Rollback restores prior version (354.1 & 354.4)
        $rolledBack = $this->service->rollbackRollout('ROLLOUT-HAUL-FLEET-V2');
        $this->assertEquals('2.0.0', $rolledBack->model_version);
        $this->assertTrue((bool) $rolledBack->is_rolled_back);
    }

    public function test_edge_inference_error_deterministic_failsafe_edge_case(): void
    {
        // 1. Normal inference executes cleanly without failsafe (354.3)
        $normal = $this->service->recordInferenceEvent(
            eventCode: 'INF-EVENT-NORMAL-01',
            deviceId: 'DEV-HAUL-TRUCK-88',
            hasModelError: false
        );
        $this->assertFalse((bool) $normal->deterministic_failsafe_engaged);
        $this->assertFalse((bool) $normal->central_alert_sent);

        // 2. Model error fails safe to deterministic rules and alerts central (354.4 & 354.5 Edge Case)
        $errorEvent = $this->service->recordInferenceEvent(
            eventCode: 'INF-EVENT-ERROR-02',
            deviceId: 'DEV-HAUL-TRUCK-89',
            hasModelError: true // Model execution error!
        );
        $this->assertTrue((bool) $errorEvent->deterministic_failsafe_engaged);
        $this->assertTrue((bool) $errorEvent->central_alert_sent);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->deployFleetModel('R-AUD', 'FLEET', '2.0', '1.0', true);
        $this->service->recordInferenceEvent('E-AUD', 'DEV-1', false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unhandled error without deterministic failsafe
        DB::table('edge_ai_inference_events')->insert([
            'event_code' => 'E-DEFECT-UNHANDLED',
            'device_id' => 'DEV-2',
            'model_execution_error' => true,
            'deterministic_failsafe_engaged' => false, // Discrepancy!
            'central_alert_sent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
