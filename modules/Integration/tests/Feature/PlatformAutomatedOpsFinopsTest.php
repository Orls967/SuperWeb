<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PlatformAutomatedOpsFinopsService;
use Tests\TestCase;

class PlatformAutomatedOpsFinopsTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformAutomatedOpsFinopsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlatformAutomatedOpsFinopsService::class);
    }

    public function test_runbook_execution_requires_dry_run_and_approval(): void
    {
        // 1. Register material runbook (297.1)
        $this->service->registerRunbookTask('RB-REPLAY-PAYMENT-DLQ-01', 'REPLAY_DLQ', true);

        // 2. Execution without dry run rejected (297.5)
        try {
            $this->service->executeRunbook('RB-REPLAY-PAYMENT-DLQ-01', 'Replayed 10 messages');
            $this->fail('Expected exception for execution without dry run');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Must complete successful dry-run simulation first', $e->getMessage());
        }

        // 3. Dry run verified and approved -> execution succeeds with evidence log (297.1 & 297.5)
        $this->service->verifyAndApproveRunbook('RB-REPLAY-PAYMENT-DLQ-01', 'LEAD_SRE_RACHMAT');
        $executed = $this->service->executeRunbook('RB-REPLAY-PAYMENT-DLQ-01', 'EVIDENCE: DLQ drained with 0 poison pills.');
        $this->assertTrue((bool) $executed->is_executed);
        $this->assertStringContainsString('DLQ drained', $executed->execution_evidence_log);
    }

    public function test_scheduled_job_overlap_guard_and_failure_alerting(): void
    {
        // 1. Acquire job lock (297.2 & 297.5)
        $this->service->acquireScheduledJobLock('JOB-HOURLY-INVOICE-RECON', 'LEAD_FINANCE_OPS');

        // 2. Overlapping schedule execution blocked (297.5)
        try {
            $this->service->acquireScheduledJobLock('JOB-HOURLY-INVOICE-RECON', 'LEAD_FINANCE_OPS');
            $this->fail('Expected exception for overlapping job run');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Overlap schedule blocked: Job', $e->getMessage());
        }

        // 3. Consecutive failures trigger owner alert (no silent retry) (297.7 Edge Case)
        $this->service->recordJobFailure('JOB-HOURLY-INVOICE-RECON'); // 1
        $this->service->recordJobFailure('JOB-HOURLY-INVOICE-RECON'); // 2
        $alertedJob = $this->service->recordJobFailure('JOB-HOURLY-INVOICE-RECON'); // 3 -> Alert!
        $this->assertTrue((bool) $alertedJob->owner_alert_sent);
        $this->assertEquals(3, (int) $alertedJob->consecutive_failure_count);
    }

    public function test_operational_readiness_review_release_gate(): void
    {
        // 1. Missing rollback plan blocks release (297.4, 297.5, 297.8)
        try {
            $this->service->evaluateReadinessReview(
                reviewCode: 'ORR-NICKEL-AUCTION-01',
                featureName: 'Live Nickel Spot Auction Module',
                onCall: true,
                dashboard: true,
                rollback: false, // Missing!
                costEstimate: true
            );
            $this->fail('Expected exception for incomplete ORR');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Feature release blocked due to missing operational readiness artifacts', $e->getMessage());
        }

        // 2. Complete ORR authorizes release (297.4 & 297.5)
        $authorized = $this->service->evaluateReadinessReview(
            reviewCode: 'ORR-NICKEL-AUCTION-02',
            featureName: 'Live Nickel Spot Auction Module',
            onCall: true,
            dashboard: true,
            rollback: true,
            costEstimate: true
        );
        $this->assertTrue((bool) $authorized->is_release_authorized);
    }

    public function test_platform_ops_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerRunbookTask('RB-AUD', 'TASK', false);
        $this->service->verifyAndApproveRunbook('RB-AUD', 'LEAD');
        $this->service->executeRunbook('RB-AUD', 'LOG');
        $this->service->acquireScheduledJobLock('JOB-AUD', 'LEAD');
        $this->service->evaluateReadinessReview('ORR-AUD', 'FEAT', true, true, true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: executed runbook without log
        DB::table('platform_automated_runbooks')->insert([
            'runbook_code' => 'RB-UNLOGGED-EXEC',
            'task_type' => 'RESTORE',
            'dry_run_verified' => true,
            'is_material_action' => false,
            'is_executed' => true,
            'execution_evidence_log' => null, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
