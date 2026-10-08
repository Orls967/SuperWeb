<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AutomationControlPlaneRemediationService;
use Tests\TestCase;

class AutomationControlPlaneRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected AutomationControlPlaneRemediationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AutomationControlPlaneRemediationService::class);
    }

    public function test_precondition_check_and_kill_switch_blocking(): void
    {
        // 1. Failed preconditions prevent action execution (364.1 & 364.4)
        try {
            $this->service->executeRemediationAction(
                actionCode: 'ACT-REPLAY-DLQ-01',
                actionType: 'REPLAY_DLQ',
                preconditionsPassed: false, // Preconditions failed!
                killSwitchActive: false
            );
            $this->fail('Expected exception for failed preconditions');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Preconditions not satisfied for action', $e->getMessage());
        }

        // 2. Active kill switch blocks pending actions (364.3 & 364.4)
        try {
            $this->service->executeRemediationAction(
                actionCode: 'ACT-SCALE-QUEUE-02',
                actionType: 'SCALE_QUEUE',
                preconditionsPassed: true,
                killSwitchActive: true // Kill switch active!
            );
            $this->fail('Expected exception for active kill switch');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Kill switch is active for remediation actions', $e->getMessage());
        }

        // 3. Normal remediation action succeeds (364.1 & 364.4)
        $act = $this->service->executeRemediationAction(
            actionCode: 'ACT-RESTART-WORKER-03',
            actionType: 'RESTART_WORKER',
            preconditionsPassed: true,
            killSwitchActive: false
        );
        $this->assertTrue((bool) $act->preconditions_satisfied);
        $this->assertTrue((bool) $act->execution_succeeded);
    }

    public function test_automation_mid_action_failure_manual_takeover_edge_case(): void
    {
        // Action failing mid-execution activates kill switch and manual takeover (364.4 & 364.5 Edge Case)
        $failedAction = $this->service->executeRemediationAction(
            actionCode: 'ACT-FAILOVER-CLUSTER-04',
            actionType: 'FAILOVER',
            preconditionsPassed: true,
            killSwitchActive: false,
            failsMidAction: true // Fails mid-action!
        );

        $this->assertTrue((bool) $failedAction->failed_mid_action);
        $this->assertTrue((bool) $failedAction->kill_switch_active);
        $this->assertTrue((bool) $failedAction->manual_takeover_engaged);
        $this->assertFalse((bool) $failedAction->execution_succeeded);
    }

    public function test_platform_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->executeRemediationAction('ACT-AUD', 'RESTART_WORKER', true, false, false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: mid-action failure without manual takeover
        DB::table('automation_control_plane_actions')->insert([
            'action_code' => 'ACT-DEFECT-HANGING',
            'action_type' => 'FAILOVER',
            'preconditions_satisfied' => true,
            'dry_run_successful' => true,
            'kill_switch_active' => false,
            'failed_mid_action' => true, // Discrepancy!
            'manual_takeover_engaged' => false, // Discrepancy!
            'execution_succeeded' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
