<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\FieldRemoteSiteOperationsIntegrityService;
use Tests\TestCase;

class FieldRemoteSiteOperationsIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected FieldRemoteSiteOperationsIntegrityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FieldRemoteSiteOperationsIntegrityService::class);
    }

    public function test_remote_task_lifecycle_and_idempotent_sync(): void
    {
        // 411.1 Initialize remote task
        $task = $this->service->initializeTask(
            taskCode: 'TSK-MINE-2026-001',
            siteName: 'South Pit Coal Concession',
            operatorId: 'OPR-4412'
        );

        $this->assertEquals('TSK-MINE-2026-001', $task->task_code);
        $this->assertEquals('pending_pre_start', $task->status);

        // 411.1 & 411.4 Clear pre-start gate
        $cleared = $this->service->clearPreStartGate(
            taskCode: 'TSK-MINE-2026-001',
            credentialsVerified: true,
            safetyPermitIssued: true,
            equipmentChecked: true
        );

        $this->assertTrue((bool) $cleared->pre_start_cleared);
        $this->assertEquals('in_progress', $cleared->status);

        // 411.2 & 411.4 Idempotent offline sync test
        $this->service->recordOfflineSync(
            taskCode: 'TSK-MINE-2026-001',
            idempotencyKey: 'SYNC-TSK-MINE-001-CHUNK1',
            payload: ['tonnage' => 120, 'fuel_litres' => 450]
        );

        // Retry same key
        $this->service->recordOfflineSync(
            taskCode: 'TSK-MINE-2026-001',
            idempotencyKey: 'SYNC-TSK-MINE-001-CHUNK1',
            payload: ['tonnage' => 120, 'fuel_litres' => 450]
        );

        $syncCount = DB::table('ops_field_offline_syncs')->where('sync_idempotency_key', 'SYNC-TSK-MINE-001-CHUNK1')->count();
        $this->assertEquals(1, $syncCount); // Zero duplicate!

        // 411.3 Seal post-operation evidence
        $sealed = $this->service->sealPostOperationEvidence(
            taskCode: 'TSK-MINE-2026-001',
            evidenceHash: 'sha256_geotagged_pod_photo_99182',
            reviewer: 'Field Safety Inspector'
        );

        $this->assertTrue((bool) $sealed->records_sealed);
        $this->assertEquals('completed', $sealed->status);

        // 411.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_pre_start_blocked_and_offline_threshold_edge_case(): void
    {
        $this->service->initializeTask('TSK-OFFSHORE-002', 'Oil Rig Alpha', 'OPR-9901');

        // 411.6 Risk: Gate cannot be bypassed if permit missing
        try {
            $this->service->clearPreStartGate(
                taskCode: 'TSK-OFFSHORE-002',
                credentialsVerified: true,
                safetyPermitIssued: false, // Missing!
                equipmentChecked: true
            );
            $this->fail('Expected exception for missing safety permit');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Pre-start gate blocked', $e->getMessage());
        }

        // 411.5 Edge case: Offline duration > 12 hours halts operation safely
        $halted = $this->service->reportOfflineDuration('TSK-OFFSHORE-002', 14);
        $this->assertTrue((bool) $halted->connectivity_safe_halt);
        $this->assertEquals('halted_safety', $halted->status);
    }
}
