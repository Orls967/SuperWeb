<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CrossLineProcessMeshService;
use Tests\TestCase;

class CrossLineProcessMeshTest extends TestCase
{
    use RefreshDatabase;

    protected CrossLineProcessMeshService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CrossLineProcessMeshService::class);
    }

    public function test_duplicate_command_deduplication_via_idempotency_key_edge_case(): void
    {
        // 1. Initial command dispatch (387.2 & 387.4)
        $first = $this->service->dispatchIdempotentCommand(
            idempotencyKey: 'IDEMP-CMD-SETTLE-INVOICE-88',
            commandPayload: '{"invoice_id": "INV-88", "amount": 5000}'
        );
        $this->assertEquals(1, $first->execution_count);

        // 2. Duplicate dispatch deduplicated without re-executing business action (387.4 & 387.5 Edge Case)
        $second = $this->service->dispatchIdempotentCommand(
            idempotencyKey: 'IDEMP-CMD-SETTLE-INVOICE-88',
            commandPayload: '{"invoice_id": "INV-88", "amount": 5000}'
        );
        $this->assertEquals(2, $second->execution_count);
        $this->assertEquals($first->id, $second->id);
    }

    public function test_event_replay_read_model_side_effect_block_risk(): void
    {
        // 1. Attempting external side effect during event replay fails (387.3, 387.4, 387.6 Risk)
        try {
            $this->service->executeReplayBatch(
                replayBatchCode: 'RPL-BATCH-LEDGER-2026',
                isReadModelHandlerOnly: false,
                attemptExternalSideEffect: true // Prohibited during replay!
            );
            $this->fail('Expected exception for replaying external side effects');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('External side effects prohibited during event replay', $e->getMessage());
        }

        // 2. Read model projection replay succeeds (387.3 & 387.4)
        $replay = $this->service->executeReplayBatch(
            replayBatchCode: 'RPL-BATCH-READMODEL-2026',
            isReadModelHandlerOnly: true,
            attemptExternalSideEffect: false
        );
        $this->assertTrue((bool) $replay->is_read_model_handler);
        $this->assertFalse((bool) $replay->external_side_effect_replayed);
    }

    public function test_event_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->dispatchIdempotentCommand('CMD-AUD', 'PAYLOAD');
        $this->service->executeReplayBatch('RPL-AUD', true, false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: replay that executed external side effect
        DB::table('global_process_mesh_event_replays')->insert([
            'replay_batch_code' => 'RPL-DEFECT-SIDE-EFFECT',
            'is_read_model_handler' => false,
            'external_side_effect_replayed' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
