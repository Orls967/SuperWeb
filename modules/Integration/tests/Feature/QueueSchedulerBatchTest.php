<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\QueueSchedulerBatchService;
use Tests\TestCase;

class QueueSchedulerBatchTest extends TestCase
{
    use RefreshDatabase;

    protected QueueSchedulerBatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QueueSchedulerBatchService::class);
    }

    public function test_poison_job_retry_budget_dlq_quarantine_risk(): void
    {
        // 1. Job exceeding retry budget gets quarantined to DLQ rather than infinite loop (394.1, 394.4, 394.6 Risk)
        try {
            $this->service->processQueueJob(
                jobCode: 'JOB-SETTLE-LEDGER-CORRUPT-01',
                retryCount: 4,
                maxRetryBudget: 3 // Exceeded!
            );
            $this->fail('Expected exception for poison message quarantine');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeded retry budget (4/3) and moved to DLQ', $e->getMessage());
        }

        // Verify quarantined record
        $job = DB::table('global_stress_queue_poison_quarantines')->where('job_code', 'JOB-SETTLE-LEDGER-CORRUPT-01')->first();
        $this->assertNotNull($job);
        $this->assertTrue((bool) $job->quarantined_to_dlq);

        // 2. Normal retry within budget succeeds without quarantine (394.4)
        $validJob = $this->service->processQueueJob(
            jobCode: 'JOB-SETTLE-LEDGER-02',
            retryCount: 2,
            maxRetryBudget: 3
        );
        $this->assertFalse((bool) $validJob->quarantined_to_dlq);
    }

    public function test_mid_flight_batch_cancellation_partial_rollback_edge_case(): void
    {
        // Cancel batch mid-flight applies consistent partial rollback (394.2 & 394.5 Edge Case)
        $checkpoint = $this->service->cancelBatchMidFlight(
            batchCode: 'BATCH-PAYROLL-RUN-2026-OCT',
            processedChunks: 45
        );

        $this->assertTrue((bool) $checkpoint->cancelled_mid_flight);
        $this->assertTrue((bool) $checkpoint->partial_rollback_applied);
        $this->assertEquals(45, $checkpoint->processed_chunks);
    }

    public function test_queue_batch_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->processQueueJob('J-AUD', 1, 3);
        $this->service->cancelBatchMidFlight('B-AUD', 10);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unquarantined poison job
        DB::table('global_stress_queue_poison_quarantines')->insert([
            'job_code' => 'J-DEFECT-UNQUARANTINED',
            'retry_count' => 10,
            'max_retry_budget' => 3,
            'quarantined_to_dlq' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
