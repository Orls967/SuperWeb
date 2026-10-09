<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CyberRansomwareRecoveryDrillService;
use Tests\TestCase;

class CyberRansomwareRecoveryDrillTest extends TestCase
{
    use RefreshDatabase;

    protected CyberRansomwareRecoveryDrillService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CyberRansomwareRecoveryDrillService::class);
    }

    public function test_ransomware_drill_containment_restore_and_reconcile_flow(): void
    {
        // 470.1 Initiate drill with instant credential revocation in isolated sandbox
        $drill = $this->service->initiateDrill(
            code: 'DRILL-RANSOM-2026-01',
            targetRpoMinutes: 15.00,
            targetRtoMinutes: 60.00,
            sandboxIsolated: true
        );

        $this->assertEquals('DRILL-RANSOM-2026-01', $drill->drill_code);
        $this->assertTrue((bool) $drill->containment_credentials_revoked);
        $this->assertEquals('contained', $drill->status);

        // 470.2 & 470.4 Restore clean backup + event replay with 0 ledger variance (RPO: 8 mins, RTO: 35 mins)
        $restored = $this->service->restoreAndReconcile(
            code: 'DRILL-RANSOM-2026-01',
            actualRpo: 8.00,
            actualRto: 35.00,
            reconcileVariance: 0.00
        );

        $this->assertEquals('restored_reconciled', $restored->status);
        $this->assertEquals(0.00, (float) $restored->ledger_reconcile_variance);

        // 470.3 Execute notification workflow
        $notified = $this->service->executeNotificationWorkflow('DRILL-RANSOM-2026-01');
        $this->assertTrue((bool) $notified->regulator_customer_notified);

        // 470.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_mid_recovery_escalation_and_ledger_variance_blocked_edge_cases(): void
    {
        // 470.5 Edge case: Mid-recovery failure escalates to war room and flags drill as failed
        $this->service->initiateDrill('DRILL-FAIL-01', 15.00, 60.00, true);

        $escalated = $this->service->restoreAndReconcile(
            code: 'DRILL-FAIL-01',
            actualRpo: 0.00,
            actualRto: 0.00,
            reconcileVariance: 0.00,
            midRecoveryFailed: true
        );

        $this->assertEquals('failed_escalated', $escalated->status);
        $this->assertTrue((bool) $escalated->war_room_escalated);

        // 470.2 & 470.4 Ledger variance is rejected
        $this->service->initiateDrill('DRILL-LEAK-01', 15.00, 60.00, true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ledger variance detected after event replay');

        $this->service->restoreAndReconcile('DRILL-LEAK-01', 5.00, 20.00, 150000.00); // Variance > 0!
    }
}
