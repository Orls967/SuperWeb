<?php

declare(strict_types=1);

namespace Modules\ControlTower\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Modules\ControlTower\Application\Services\ControlTowerAuditReportService;
use Modules\ControlTower\Application\Services\SopPublicationOrchestrator;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\Models\SopEvent;
use Modules\ControlTower\Domain\StateMachines\SopCycleStateMachine;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Application\Services\EventSchemaRegistry;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Domain\Models\ApprovalRequest;
use Modules\Core\Domain\Models\CoreEventSchema;
use Modules\Core\Domain\Models\CoreTask;
use Modules\Core\Domain\Models\OutboxMessage;
use RuntimeException;
use Tests\TestCase;

class Slice4SopCycleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_machine_supports_canonical_and_legacy_paths_and_backward_reason_enforcement(): void
    {
        $sm = new SopCycleStateMachine;

        $this->assertSame(
            ['data_gathering', 'demand_review', 'supply_review', 'pre_sop', 'exec_sop', 'published', 'closed'],
            SopCycleStateMachine::canonicalStates(),
        );

        // Canonical 7-stage forward path
        $state = 'data_gathering';
        foreach (['demand_review', 'supply_review', 'pre_sop', 'exec_sop', 'published', 'closed'] as $next) {
            $this->assertTrue($sm->canTransition($state, $next));
            $this->assertFalse($sm->isBackwardTransition($state, $next));
            $sm->assertTransitionWithReason($state, $next, null);
            $state = $next;
        }

        // Legacy 7-stage forward path
        $legacyState = 'draft';
        foreach (['demand_review', 'supply_review', 'pre_sop_balancing', 'executive_signoff', 'published', 'archived'] as $next) {
            $this->assertTrue($sm->canTransition($legacyState, $next));
            $legacyState = $next;
        }

        // Backward transitions require non-empty reason
        foreach ([
            ['supply_review', 'demand_review'],
            ['pre_sop', 'supply_review'],
            ['exec_sop', 'pre_sop'],
            ['demand_review', 'data_gathering'],
        ] as [$from, $to]) {
            $this->assertTrue($sm->isBackwardTransition($from, $to));
            $sm->assertTransitionWithReason($from, $to, 'Revisi kapasitas produksi');

            try {
                $sm->assertTransitionWithReason($from, $to, '   ');
                $this->fail("Expected InvalidArgumentException for empty reason on {$from} -> {$to}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('BACKWARD_TRANSITION_REASON_REQUIRED', $e->getMessage());
            }
        }
    }

    public function test_full_7_stage_canonical_lifecycle_with_task_inbox_approval_and_outbox_event(): void
    {
        $planner = User::factory()->create(['role' => 'planner']);
        $executive = User::factory()->create(['role' => 'admin']);

        /** @var SopPublicationOrchestrator $orchestrator */
        $orchestrator = app(SopPublicationOrchestrator::class);

        // 1. data_gathering
        $cycle = $orchestrator->startCanonicalCycle(
            period: '2026-11',
            scenarioType: 'moderate',
            demandPlanQty: 12000,
            supplyPlanQty: 11800,
            capacityUtilizationBps: 8400,
            grossMarginEstIdr: 2_500_000_000,
            cashImpactEstIdr: 450_000_000,
            actorUserId: $planner->id,
            notes: 'Siklus S&OP November 2026',
        );

        $this->assertSame('data_gathering', $cycle->status);
        $this->assertSame($planner->id, $cycle->created_by_user_id);

        // 2. demand_review -> creates TaskInbox item for planner
        $cycle = $orchestrator->advanceStage($cycle, 'demand_review', $planner->id);
        $this->assertSame('demand_review', $cycle->status);
        $this->assertNotNull($cycle->task_id);
        $this->assertDatabaseHas('core_tasks', [
            'id' => $cycle->task_id,
            'module' => 'ControlTower',
            'source_entity_type' => SopCycle::class,
            'source_entity_id' => (string) $cycle->id,
            'assignee_role' => 'planner',
        ]);

        // 3. supply_review -> creates TaskInbox item
        $cycle = $orchestrator->advanceStage($cycle, 'supply_review', $planner->id);
        $this->assertSame('supply_review', $cycle->status);

        // 4. pre_sop -> creates TaskInbox item
        $cycle = $orchestrator->advanceStage($cycle, 'pre_sop', $planner->id);
        $this->assertSame('pre_sop', $cycle->status);
        $this->assertSame(3, CoreTask::query()->where('source_entity_id', (string) $cycle->id)->count());

        // 5. submitForExecutiveApproval (pre_sop -> exec_sop) -> submits to ApprovalEngine
        $cycle = $orchestrator->submitForExecutiveApproval($cycle, $planner);
        $this->assertSame('exec_sop', $cycle->status);
        $this->assertNotNull($cycle->approval_id);
        $this->assertDatabaseHas('core_approvals', [
            'id' => $cycle->approval_id,
            'approval_type' => 'SOP_CONSENSUS',
            'created_by' => $planner->id,
            'status' => 'pending',
        ]);

        // Four-eyes enforcement: creator cannot publish/sign-off own S&OP cycle
        try {
            $orchestrator->publishConsensus($cycle, $planner, 'Self approve attempt');
            $this->fail('Expected RuntimeException for four-eyes violation');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Four-Eyes', $e->getMessage());
        }

        // 6. publishConsensus by distinct executive -> approved + published + schema registered + outbox recorded
        $cycle = $orchestrator->publishConsensus(
            cycle: $cycle,
            signOffUser: $executive,
            notes: 'Konsensus eksekutif disetujui',
        );

        $this->assertSame('published', $cycle->status);
        $this->assertNotNull($cycle->published_at);
        $this->assertSame($executive->id, $cycle->published_by_user_id);
        $this->assertSame($executive->id, $cycle->signed_off_by_user_id);
        $this->assertIsArray($cycle->consensus_snapshot_json);
        $this->assertSame(-200, $cycle->consensus_snapshot_json['gap_qty']);

        $approval = ApprovalRequest::query()->find($cycle->approval_id);
        $this->assertNotNull($approval);
        $this->assertSame('approved', $approval->status);

        // EventSchemaRegistry registered tower.sop.published.v1
        $schema = CoreEventSchema::query()
            ->where('event_name', 'tower.sop.published.v1')
            ->where('version', 1)
            ->first();
        $this->assertNotNull($schema);
        $this->assertSame('ControlTower', $schema->producer_module);

        // OutboxMessage recorded tower.sop.published.v1
        $outbox = OutboxMessage::query()
            ->where('event_type', 'tower.sop.published.v1')
            ->where('aggregate_id', (string) $cycle->id)
            ->first();
        $this->assertNotNull($outbox);
        $this->assertSame($cycle->cycle_code, $outbox->payload['cycle_code']);
        $this->assertSame(12000, $outbox->payload['demand_plan_qty']);
        $this->assertSame(11800, $outbox->payload['supply_plan_qty']);
        $this->assertSame($executive->id, $outbox->payload['approved_by_user_id']);

        // 7. closeCycle (published -> closed)
        $cycle = $orchestrator->closeCycle($cycle, $executive->id, 'Periode November ditutup');
        $this->assertSame('closed', $cycle->status);

        // ControlTower audit passes with 0 violations for canonical states
        $report = app(ControlTowerAuditReportService::class)->buildDetailedReport();
        $this->assertSame('CLEAN', $report['status']);
    }

    public function test_backward_transition_fails_without_reason_and_succeeds_with_reason_recording_sop_event(): void
    {
        $planner = User::factory()->create(['role' => 'planner']);

        /** @var SopPublicationOrchestrator $orchestrator */
        $orchestrator = app(SopPublicationOrchestrator::class);

        $cycle = $orchestrator->startCanonicalCycle(
            period: '2026-12',
            scenarioType: 'conservative',
            demandPlanQty: 8000,
            supplyPlanQty: 7500,
            actorUserId: $planner->id,
        );
        $cycle = $orchestrator->advanceStage($cycle, 'demand_review', $planner->id);
        $cycle = $orchestrator->advanceStage($cycle, 'supply_review', $planner->id);

        // Fails without reason
        try {
            $orchestrator->advanceStage($cycle, 'demand_review', $planner->id, null);
            $this->fail('Expected InvalidArgumentException when rolling back without reason');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('BACKWARD_TRANSITION_REASON_REQUIRED', $e->getMessage());
        }

        $cycle->refresh();
        $this->assertSame('supply_review', $cycle->status);

        // Succeeds with reason and records SopEvent
        $cycle = $orchestrator->advanceStage(
            cycle: $cycle,
            toStatus: 'demand_review',
            actorUserId: $planner->id,
            reason: 'Lonjakan permintaan promo akhir tahun perlu review ulang',
        );

        $this->assertSame('demand_review', $cycle->status);

        $event = SopEvent::query()
            ->where('sop_cycle_id', $cycle->id)
            ->where('from_status', 'supply_review')
            ->where('to_status', 'demand_review')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($planner->id, $event->actor_user_id);
        $this->assertSame('Lonjakan permintaan promo akhir tahun perlu review ulang', $event->notes);
    }

    public function test_orchestrator_falls_back_to_laravel_event_dispatcher_when_outbox_bus_is_absent(): void
    {
        Event::fake(['tower.sop.published.v1']);

        $planner = User::factory()->create(['role' => 'planner']);
        $approver = User::factory()->create(['role' => 'admin']);

        $orchestrator = new SopPublicationOrchestrator(
            stateMachine: new SopCycleStateMachine,
            clock: app(ClockInterface::class),
            taskInbox: app(TaskInboxService::class),
            approvalEngine: app(ApprovalEngineInterface::class),
            outboxBus: null,
            schemaRegistry: app(EventSchemaRegistry::class),
        );

        $cycle = $orchestrator->startCanonicalCycle('2026-10', 'moderate', 5000, 5000, actorUserId: $planner->id);
        $cycle = $orchestrator->advanceStage($cycle, 'demand_review', $planner->id);
        $cycle = $orchestrator->advanceStage($cycle, 'supply_review', $planner->id);
        $cycle = $orchestrator->advanceStage($cycle, 'pre_sop', $planner->id);
        $cycle = $orchestrator->submitForExecutiveApproval($cycle, $planner);
        $cycle = $orchestrator->publishConsensus($cycle, $approver, 'Fallback dispatch test');

        $this->assertSame('published', $cycle->status);
        Event::assertDispatched('tower.sop.published.v1', static function (string $eventName, array $payload) use ($cycle): bool {
            return $eventName === 'tower.sop.published.v1'
                && ($payload[0]['cycle_code'] ?? null) === $cycle->cycle_code;
        });
    }

    public function test_consensus_endpoint_and_csv_export_respect_roles_and_workflow(): void
    {
        $planner = User::factory()->create(['role' => 'planner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $auditor = User::factory()->create(['role' => 'auditor']);
        $customer = User::factory()->create(['role' => 'customer']);

        /** @var SopPublicationOrchestrator $orchestrator */
        $orchestrator = app(SopPublicationOrchestrator::class);
        $cycle = $orchestrator->startCanonicalCycle(
            period: '2026-11',
            scenarioType: 'aggressive',
            demandPlanQty: 15000,
            supplyPlanQty: 14500,
            capacityUtilizationBps: 9100,
            grossMarginEstIdr: 3_100_000_000,
            cashImpactEstIdr: 600_000_000,
            actorUserId: $planner->id,
        );

        // Unauthorized customer blocked (403)
        $this->actingAs($customer)
            ->postJson(route('control_tower.sop.consensus', ['cycle' => $cycle->id]), [
                'to_status' => 'demand_review',
            ])
            ->assertForbidden();

        // Auditor cannot mutate consensus (403)
        $this->actingAs($auditor)
            ->postJson(route('control_tower.sop.consensus', ['cycle' => $cycle->id]), [
                'to_status' => 'demand_review',
            ])
            ->assertForbidden();

        // Planner advances through stages via HTTP
        $this->actingAs($planner)
            ->postJson(route('control_tower.sop.consensus', ['cycle' => $cycle->id]), [
                'to_status' => 'demand_review',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'demand_review');

        $this->actingAs($planner)
            ->postJson(route('control_tower.sop.consensus', ['cycle' => $cycle->id]), [
                'to_status' => 'supply_review',
            ])
            ->assertOk();

        // Rollback without reason returns 422
        $this->actingAs($planner)
            ->postJson(route('control_tower.sop.consensus', ['cycle' => $cycle->id]), [
                'action' => 'rollback',
                'to_status' => 'demand_review',
            ])
            ->assertStatus(422);

        // Rollback with reason returns 200
        $this->actingAs($planner)
            ->postJson(route('control_tower.sop.consensus', ['cycle' => $cycle->id]), [
                'action' => 'rollback',
                'to_status' => 'demand_review',
                'reason' => 'Penyesuaian alokasi gudang barat',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'demand_review');

        // Advance back to supply_review -> pre_sop -> exec_sop
        $this->actingAs($planner)
            ->postJson(route('control_tower.sop.consensus', ['cycle' => $cycle->id]), [
                'to_status' => 'supply_review',
            ])->assertOk();

        $this->actingAs($planner)
            ->postJson(route('control_tower.sop.consensus', ['cycle' => $cycle->id]), [
                'to_status' => 'pre_sop',
            ])->assertOk();

        $this->actingAs($planner)
            ->postJson(route('control_tower.sop.consensus', ['cycle' => $cycle->id]), [
                'action' => 'submit_approval',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'exec_sop');

        // Same creator cannot publish (422 four-eyes)
        $this->actingAs($planner)
            ->postJson(route('control_tower.sop.consensus', ['cycle' => $cycle->id]), [
                'action' => 'publish',
                'reason' => 'Self publish blocked',
            ])
            ->assertStatus(422);

        // Admin approves & publishes
        $this->actingAs($admin)
            ->postJson(route('control_tower.sop.consensus', ['cycle' => $cycle->id]), [
                'action' => 'publish',
                'reason' => 'Approved for execution',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        // CSV Export accessible by auditor, denied to customer
        $this->actingAs($customer)
            ->get(route('control_tower.sop.export'))
            ->assertForbidden();

        $csvResponse = $this->actingAs($auditor)
            ->get(route('control_tower.sop.export'));

        $csvResponse->assertOk();
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('cycle_code,period,status,scenario_type', $csvContent);
        $this->assertStringContainsString($cycle->cycle_code, $csvContent);
        $this->assertStringContainsString('published', $csvContent);
    }
}
