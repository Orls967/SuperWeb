<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EventSpineReplayCenterService;
use Tests\TestCase;

class EventSpineReplayCenterTest extends TestCase
{
    use RefreshDatabase;

    protected EventSpineReplayCenterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EventSpineReplayCenterService::class);
    }

    public function test_unauthorized_replay_and_double_post_blocked(): void
    {
        // 1. Unauthorized replay is denied (369.2 & 369.4)
        try {
            $this->service->executeEventReplay(
                replayCode: 'REPLAY-INV-001',
                eventTopic: 'orders.fulfillment.completed',
                approvalGranted: false // Unauthorized!
            );
            $this->fail('Expected exception for unauthorized event replay');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unauthorized event replay is strictly denied', $e->getMessage());
        }

        // 2. Replay attempting double-post to ledger is blocked (369.4)
        try {
            $this->service->executeEventReplay(
                replayCode: 'REPLAY-LEDGER-002',
                eventTopic: 'payments.settled',
                approvalGranted: true,
                disableExternalSideEffects: true,
                attemptDoublePostLedger: true // Attempt double-post!
            );
            $this->fail('Expected exception for double-post ledger replay');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Event replay prevented from double-posting financial ledger', $e->getMessage());
        }

        // 3. Authorized replay disables external side-effects and succeeds (369.4 & 369.5 Edge Case)
        $replay = $this->service->executeEventReplay(
            replayCode: 'REPLAY-ORDERS-003',
            eventTopic: 'orders.created',
            approvalGranted: true,
            disableExternalSideEffects: true
        );
        $this->assertTrue((bool) $replay->approval_granted);
        $this->assertTrue((bool) $replay->external_side_effects_disabled);
        $this->assertTrue((bool) $replay->replay_succeeded);
    }

    public function test_dlq_aging_ownerless_escalation_risk(): void
    {
        // 1. DLQ with owner does not escalate prematurely (369.3)
        $withOwner = $this->service->triageDlqItem(
            dlqCode: 'DLQ-CLAIM-EVENT-01',
            eventTopic: 'claims.submitted',
            assignedOwner: 'DEV-CLAIMS-SUPPORT',
            ageHours: 48
        );
        $this->assertFalse((bool) $withOwner->aging_alert_escalated);

        // 2. Ownerless DLQ aged >= 24 hours triggers escalation alert (369.6 Risk)
        $ownerlessAged = $this->service->triageDlqItem(
            dlqCode: 'DLQ-MINE-TELEMETRY-02',
            eventTopic: 'iot.haul.telemetry',
            assignedOwner: null, // Ownerless!
            ageHours: 36 // Aged 36h!
        );
        $this->assertTrue((bool) $ownerlessAged->aging_alert_escalated);
    }

    public function test_event_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->executeEventReplay('R-AUD', 'topic', true, true, false);
        $this->service->triageDlqItem('D-AUD', 'topic', 'OWNER', 1);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: ownerless aged DLQ without escalation
        DB::table('platform_event_spine_dlq_triages')->insert([
            'dlq_code' => 'D-DEFECT-UNESCALATED',
            'event_topic' => 'topic',
            'assigned_owner' => null, // Discrepancy!
            'age_hours' => 50,
            'aging_alert_escalated' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
