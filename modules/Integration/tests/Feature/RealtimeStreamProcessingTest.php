<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\RealtimeStreamProcessingService;
use Tests\TestCase;

class RealtimeStreamProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected RealtimeStreamProcessingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RealtimeStreamProcessingService::class);
    }

    public function test_event_ingestion_and_sequential_offsets(): void
    {
        $evt1 = $this->service->ingestEvent(
            eventId: 'EVT-TX-001',
            partitionKey: 'ACCT-99',
            streamTopic: 'PAYMENT_TRANSACTIONS',
            isMonetary: true,
            payload: ['amount' => 500000.0, 'currency' => 'IDR']
        );

        $evt2 = $this->service->ingestEvent(
            eventId: 'EVT-TX-002',
            partitionKey: 'ACCT-99',
            streamTopic: 'PAYMENT_TRANSACTIONS',
            isMonetary: true,
            payload: ['amount' => 250000.0, 'currency' => 'IDR']
        );

        $this->assertEquals(1, $evt1->offset_number);
        $this->assertEquals(2, $evt2->offset_number);
        $this->assertTrue((bool) $evt1->is_monetary);
    }

    public function test_consumer_checkpoint_lag_and_sla_alert(): void
    {
        // Ingest 600 events
        for ($i = 1; $i <= 600; $i++) {
            $this->service->ingestEvent(
                eventId: "EVT-STREAM-{$i}",
                partitionKey: 'PART-A',
                streamTopic: 'ANALYTICS_CLICKS',
                isMonetary: false,
                payload: ['click_id' => $i]
            );
        }

        // Consumer processed up to offset 50 (lag = 550, threshold = 500)
        $checkpoint = $this->service->updateConsumerCheckpoint(
            consumerId: 'CLICKSTREAM_PIPELINE',
            processedOffset: 50,
            streamTopic: 'ANALYTICS_CLICKS',
            slaLagThreshold: 500
        );

        $this->assertEquals(550, (int) $checkpoint->current_stream_lag);
        $this->assertTrue((bool) $checkpoint->lag_alert_triggered);
    }

    public function test_severe_lag_triggers_snapshot_resync_and_remediation(): void
    {
        // Simulate topic at offset 6000
        DB::table('data_stream_messages')->insert([
            'event_id' => 'EVT-OFFSET-6000',
            'partition_key' => 'BULK',
            'stream_topic' => 'LEDGER_CDC',
            'is_monetary' => true,
            'offset_number' => 6000,
            'payload_json' => json_encode(['amount' => 100.0]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Consumer at offset 100 (lag = 5900 > 5000) (242.6 Edge Case)
        $checkpoint = $this->service->updateConsumerCheckpoint(
            consumerId: 'SLOW_AUDIT_CONSUMER',
            processedOffset: 100,
            streamTopic: 'LEDGER_CDC',
            slaLagThreshold: 500
        );

        $this->assertTrue((bool) $checkpoint->resync_from_snapshot_required);

        // Perform snapshot resync
        $resynced = $this->service->resyncConsumerFromSnapshot('SLOW_AUDIT_CONSUMER', 6000);
        $this->assertEquals(6000, (int) $resynced->last_processed_offset);
        $this->assertEquals(0, (int) $resynced->current_stream_lag);
        $this->assertFalse((bool) $resynced->resync_from_snapshot_required);
    }

    public function test_backpressure_policy_sheds_analytics_never_drops_money(): void
    {
        // Simulate high backpressure under OVERLOADED status (242.4, 242.5, 242.7)
        $buffer = $this->service->handleBackpressure(
            loadStatus: 'OVERLOADED',
            incomingAnalyticsCount: 1000,
            incomingMonetaryCount: 200
        );

        // Monetary queue must have ZERO drops
        $this->assertEquals(0, (int) $buffer->monetary_queue_dropped);
        // Telemetry analytics may be shed
        $this->assertGreaterThan(0, (int) $buffer->analytics_queue_dropped);
        $this->assertEquals(800, (int) $buffer->analytics_queue_dropped); // 80% shed
    }

    public function test_event_replay_time_travel_and_mismatch_detection(): void
    {
        // 1. Ingest sequence of events with monetary amounts
        $this->service->ingestEvent('EVT-R-1', 'ACC', 'FINANCE', true, ['amount' => 1000.0]);
        $this->service->ingestEvent('EVT-R-2', 'ACC', 'FINANCE', true, ['amount' => 2500.0]);
        $this->service->ingestEvent('EVT-R-3', 'ACC', 'FINANCE', true, ['amount' => 1500.0]);
        // Total = 5000.0

        // 2. Replay with matching batch ledger (242.3 & 242.5)
        $cleanReplay = $this->service->replayStreamEvents(1, 3, 5000.0);
        $this->assertEquals(5000.0, (float) $cleanReplay->replay_aggregate_sum);
        $this->assertFalse((bool) $cleanReplay->is_mismatch_detected);

        // 3. Replay with mismatch (batch expects 4200.0) -> flags incident
        $mismatchReplay = $this->service->replayStreamEvents(1, 3, 4200.0);
        $this->assertTrue((bool) $mismatchReplay->is_mismatch_detected);
    }

    public function test_stream_processing_audit_healthy_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->ingestEvent('EVT-H-1', 'A', 'CORE', true, ['amount' => 10.0]);
        $this->service->handleBackpressure('NORMAL', 100, 50);
        $this->service->replayStreamEvents(1, 1, 10.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: dropped monetary transaction!
        DB::table('data_stream_backpressure_buffers')->insert([
            'buffer_code' => 'BP-VIOLATION',
            'system_load_status' => 'CRASH',
            'monetary_queue_dropped' => 3, // Inviolable rule broken!
            'analytics_queue_dropped' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
