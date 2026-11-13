<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\Models\SopEvent;
use Modules\ControlTower\Domain\StateMachines\SopCycleStateMachine;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Application\Services\EventSchemaRegistry;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\OutboxBusInterface;

final class SopPublicationOrchestrator
{
    public const EVENT_SOP_PUBLISHED = 'tower.sop.published.v1';

    public function __construct(
        private readonly SopCycleStateMachine $stateMachine,
        private readonly ClockInterface $clock,
        private readonly ?TaskInboxService $taskInbox = null,
        private readonly ?ApprovalEngineInterface $approvalEngine = null,
        private readonly ?OutboxBusInterface $outboxBus = null,
        private readonly ?EventSchemaRegistry $schemaRegistry = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $consensusSnapshot
     */
    public function startCanonicalCycle(
        string $period,
        string $scenarioType = 'moderate',
        int $demandPlanQty = 0,
        int $supplyPlanQty = 0,
        int $capacityUtilizationBps = 0,
        int $grossMarginEstIdr = 0,
        int $cashImpactEstIdr = 0,
        ?int $actorUserId = null,
        ?string $notes = null,
        ?array $consensusSnapshot = null,
    ): SopCycle {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new InvalidArgumentException('INVALID_PERIOD: Format periode harus YYYY-MM.');
        }

        if ($demandPlanQty < 0 || $supplyPlanQty < 0) {
            throw new InvalidArgumentException('NEGATIVE_PLAN_QTY: Rencana demand/supply tidak boleh negatif.');
        }

        $allowedScenarios = ['conservative', 'moderate', 'aggressive'];
        $normalizedScenario = strtolower($scenarioType);
        if (! in_array($normalizedScenario, $allowedScenarios, true)) {
            throw new InvalidArgumentException('INVALID_SCENARIO_TYPE: Skenario harus conservative, moderate, atau aggressive.');
        }

        return DB::transaction(function () use (
            $period,
            $normalizedScenario,
            $demandPlanQty,
            $supplyPlanQty,
            $capacityUtilizationBps,
            $grossMarginEstIdr,
            $cashImpactEstIdr,
            $actorUserId,
            $notes,
            $consensusSnapshot,
        ): SopCycle {
            $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $cycle = SopCycle::create([
                'cycle_code' => 'SOP-'.str_replace('-', '', $period).'-'.$suffix,
                'period' => $period,
                'status' => 'data_gathering',
                'scenario_type' => $normalizedScenario,
                'demand_plan_qty' => $demandPlanQty,
                'supply_plan_qty' => $supplyPlanQty,
                'capacity_utilization_bps' => max(0, min(20_000, $capacityUtilizationBps)),
                'gross_margin_est_idr' => $grossMarginEstIdr,
                'cash_impact_est_idr' => $cashImpactEstIdr,
                'consensus_snapshot_json' => $consensusSnapshot,
                'notes' => $notes,
                'created_by_user_id' => $actorUserId,
            ]);

            SopEvent::create([
                'sop_cycle_id' => $cycle->id,
                'from_status' => 'none',
                'to_status' => 'data_gathering',
                'actor_user_id' => $actorUserId,
                'notes' => $this->truncateEventNote($notes ?? 'Inisiasi siklus S&OP kanonik (data_gathering)'),
                'created_at' => $this->clock->now(),
            ]);

            return $cycle;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function advanceStage(
        SopCycle $cycle,
        string $toStatus,
        ?int $actorUserId = null,
        ?string $reason = null,
        array $payload = [],
    ): SopCycle {
        if ($toStatus === 'published') {
            $snapshot = isset($payload['consensus_snapshot_json']) && is_array($payload['consensus_snapshot_json'])
                ? $payload['consensus_snapshot_json']
                : null;

            return $this->publishConsensus($cycle, $actorUserId, $reason, $snapshot);
        }

        if ($toStatus === 'closed') {
            return $this->closeCycle($cycle, $actorUserId, $reason);
        }

        return DB::transaction(function () use ($cycle, $toStatus, $actorUserId, $reason, $payload): SopCycle {
            /** @var SopCycle $locked */
            $locked = SopCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $from = $locked->status;

            $this->stateMachine->assertTransitionWithReason($from, $toStatus, $reason);

            $updates = ['status' => $toStatus];

            foreach (['demand_plan_qty', 'supply_plan_qty', 'capacity_utilization_bps', 'gross_margin_est_idr', 'cash_impact_est_idr'] as $intField) {
                if (array_key_exists($intField, $payload) && $payload[$intField] !== null) {
                    $updates[$intField] = (int) $payload[$intField];
                }
            }

            if (array_key_exists('consensus_snapshot_json', $payload) && is_array($payload['consensus_snapshot_json'])) {
                $updates['consensus_snapshot_json'] = $payload['consensus_snapshot_json'];
            }

            if ($reason !== null && trim($reason) !== '') {
                $updates['notes'] = trim($reason);
            }

            if ($toStatus === 'executive_signoff') {
                $updates['signed_off_by_user_id'] = $actorUserId;
                $updates['signed_off_at'] = $this->clock->now();
            }

            if (in_array($toStatus, ['demand_review', 'supply_review', 'pre_sop', 'pre_sop_balancing'], true) && $this->taskInbox !== null) {
                $task = $this->taskInbox->createTask([
                    'title' => sprintf('S&OP %s: %s (%s)', $locked->cycle_code, $this->stageLabel($toStatus), $locked->period),
                    'description' => sprintf(
                        'Tinjauan tahap %s untuk siklus S&OP %s periode %s.',
                        $toStatus,
                        $locked->cycle_code,
                        $locked->period
                    ),
                    'module' => 'ControlTower',
                    'source_entity_type' => SopCycle::class,
                    'source_entity_id' => (string) $locked->id,
                    'assignee_user_id' => isset($payload['assignee_user_id']) ? (int) $payload['assignee_user_id'] : null,
                    'assignee_role' => 'planner',
                    'status' => 'pending',
                    'priority' => 'high',
                    'action_url' => '/control-tower/sop',
                    'payload' => [
                        'cycle_code' => $locked->cycle_code,
                        'period' => $locked->period,
                        'from_status' => $from,
                        'to_status' => $toStatus,
                    ],
                    'creator_id' => $actorUserId,
                ]);

                if (isset($task->id)) {
                    $updates['task_id'] = (string) $task->id;
                }
            }

            $locked->update($updates);

            SopEvent::create([
                'sop_cycle_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'actor_user_id' => $actorUserId,
                'notes' => $this->truncateEventNote(
                    $reason !== null && trim($reason) !== ''
                        ? trim($reason)
                        : "Transisi {$from} -> {$toStatus}"
                ),
                'created_at' => $this->clock->now(),
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * @param  array<int, array{role?: string|null, user_id?: int}>  $steps
     */
    public function submitForExecutiveApproval(
        SopCycle $cycle,
        User|int|null $creator = null,
        array $steps = [],
    ): SopCycle {
        return DB::transaction(function () use ($cycle, $creator, $steps): SopCycle {
            /** @var SopCycle $locked */
            $locked = SopCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $from = $locked->status;

            $creatorUser = $this->resolveUser($creator)
                ?? ($locked->created_by_user_id !== null ? User::query()->find((int) $locked->created_by_user_id) : null);

            $targetStatus = match ($from) {
                'pre_sop' => 'exec_sop',
                'pre_sop_balancing' => 'executive_signoff',
                'exec_sop', 'executive_signoff' => $from,
                default => 'exec_sop',
            };

            if ($targetStatus !== $from) {
                $this->stateMachine->assertTransition($from, $targetStatus);
            }

            $updates = [];
            if ($targetStatus !== $from) {
                $updates['status'] = $targetStatus;
            }
            if ($locked->created_by_user_id === null && $creatorUser !== null) {
                $updates['created_by_user_id'] = $creatorUser->id;
            }

            if ($this->approvalEngine !== null && $creatorUser !== null && $locked->approval_id === null) {
                $approval = $this->approvalEngine->submit(
                    approvalType: 'SOP_CONSENSUS',
                    title: "Persetujuan Eksekutif S&OP {$locked->cycle_code} ({$locked->period})",
                    creator: $creatorUser,
                    approvable: $locked,
                    amount: (string) $locked->gross_margin_est_idr,
                    steps: $steps !== [] ? $steps : [['role' => null]],
                    slaHours: 48,
                    metadata: [
                        'cycle_code' => $locked->cycle_code,
                        'period' => $locked->period,
                        'scenario_type' => $locked->scenario_type,
                        'demand_plan_qty' => (int) $locked->demand_plan_qty,
                        'supply_plan_qty' => (int) $locked->supply_plan_qty,
                    ],
                );

                if (isset($approval->id)) {
                    $updates['approval_id'] = (int) $approval->id;
                }
            }

            if ($updates !== []) {
                $locked->update($updates);
            }

            if ($targetStatus !== $from) {
                SopEvent::create([
                    'sop_cycle_id' => $locked->id,
                    'from_status' => $from,
                    'to_status' => $targetStatus,
                    'actor_user_id' => $creatorUser?->id,
                    'notes' => "Pengajuan persetujuan eksekutif ({$from} -> {$targetStatus})",
                    'created_at' => $this->clock->now(),
                ]);
            }

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * @param  array<string, mixed>|null  $consensusSnapshot
     */
    public function publishConsensus(
        SopCycle $cycle,
        User|int|null $signOffUser = null,
        ?string $notes = null,
        ?array $consensusSnapshot = null,
    ): SopCycle {
        return DB::transaction(function () use ($cycle, $signOffUser, $notes, $consensusSnapshot): SopCycle {
            /** @var SopCycle $locked */
            $locked = SopCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $from = $locked->status;

            $this->stateMachine->assertTransition($from, 'published');

            $approver = $this->resolveUser($signOffUser);
            $approvalId = $locked->approval_id;

            if ($this->approvalEngine !== null && $approver !== null) {
                if ($approvalId !== null) {
                    $this->approvalEngine->approve((int) $approvalId, $approver, $notes);
                } else {
                    $creatorUser = $this->resolveCreatorForApproval($locked);
                    if ($creatorUser !== null) {
                        $approval = $this->approvalEngine->submit(
                            approvalType: 'SOP_CONSENSUS',
                            title: "Persetujuan Eksekutif S&OP {$locked->cycle_code} ({$locked->period})",
                            creator: $creatorUser,
                            approvable: $locked,
                            amount: (string) $locked->gross_margin_est_idr,
                            steps: [['role' => null]],
                            slaHours: 48,
                            metadata: [
                                'cycle_code' => $locked->cycle_code,
                                'period' => $locked->period,
                            ],
                        );
                        $this->approvalEngine->approve($approval, $approver, $notes);
                        if (isset($approval->id)) {
                            $approvalId = (int) $approval->id;
                        }
                    }
                }
            }

            $now = $this->clock->now();
            $snapshot = $consensusSnapshot ?? $locked->consensus_snapshot_json ?? [
                'cycle_code' => $locked->cycle_code,
                'period' => $locked->period,
                'scenario_type' => $locked->scenario_type,
                'demand_plan_qty' => (int) $locked->demand_plan_qty,
                'supply_plan_qty' => (int) $locked->supply_plan_qty,
                'gap_qty' => (int) $locked->supply_plan_qty - (int) $locked->demand_plan_qty,
                'capacity_utilization_bps' => (int) $locked->capacity_utilization_bps,
                'gross_margin_est_idr' => (int) $locked->gross_margin_est_idr,
                'cash_impact_est_idr' => (int) $locked->cash_impact_est_idr,
                'published_at' => $now->toIso8601String(),
            ];

            $locked->update([
                'status' => 'published',
                'approval_id' => $approvalId,
                'signed_off_by_user_id' => $approver?->id ?? $locked->signed_off_by_user_id,
                'signed_off_at' => $approver !== null ? $now : $locked->signed_off_at,
                'published_at' => $now,
                'published_by_user_id' => $approver?->id,
                'consensus_snapshot_json' => $snapshot,
                'notes' => $notes ?? $locked->notes,
            ]);

            SopEvent::create([
                'sop_cycle_id' => $locked->id,
                'from_status' => $from,
                'to_status' => 'published',
                'actor_user_id' => $approver?->id,
                'notes' => $this->truncateEventNote($notes ?? 'Konsensus S&OP dipublikasikan'),
                'created_at' => $now,
            ]);

            $this->emitPublishedEvent($locked->fresh() ?? $locked, $approver);

            return $locked->fresh() ?? $locked;
        });
    }

    public function closeCycle(
        SopCycle $cycle,
        ?int $actorUserId = null,
        ?string $notes = null,
    ): SopCycle {
        return DB::transaction(function () use ($cycle, $actorUserId, $notes): SopCycle {
            /** @var SopCycle $locked */
            $locked = SopCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $from = $locked->status;

            $this->stateMachine->assertTransition($from, 'closed');

            $locked->update([
                'status' => 'closed',
                'notes' => $notes ?? $locked->notes,
            ]);

            SopEvent::create([
                'sop_cycle_id' => $locked->id,
                'from_status' => $from,
                'to_status' => 'closed',
                'actor_user_id' => $actorUserId,
                'notes' => $this->truncateEventNote($notes ?? 'Siklus S&OP ditutup (closed)'),
                'created_at' => $this->clock->now(),
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    private function emitPublishedEvent(SopCycle $cycle, ?User $approver): void
    {
        $approvedByUserId = (int) ($approver?->id ?? $cycle->published_by_user_id ?? $cycle->signed_off_by_user_id ?? $cycle->created_by_user_id ?? 0);
        $idempotencyKey = sprintf('%s:%s:%d', self::EVENT_SOP_PUBLISHED, $cycle->cycle_code, $cycle->id);

        $payload = [
            'event_id' => (string) Str::uuid(),
            'occurred_at' => $this->clock->now()->toIso8601String(),
            'aggregate_type' => SopCycle::class,
            'aggregate_id' => (string) $cycle->id,
            'idempotency_key' => $idempotencyKey,
            'schema_version' => 1,
            'cycle_code' => $cycle->cycle_code,
            'period' => $cycle->period,
            'scenario_type' => $cycle->scenario_type,
            'demand_plan_qty' => (int) $cycle->demand_plan_qty,
            'supply_plan_qty' => (int) $cycle->supply_plan_qty,
            'gap_qty' => (int) $cycle->supply_plan_qty - (int) $cycle->demand_plan_qty,
            'approved_by_user_id' => $approvedByUserId,
            'data' => [
                'cycle_code' => $cycle->cycle_code,
                'period' => $cycle->period,
                'demand_plan_qty' => (int) $cycle->demand_plan_qty,
                'supply_plan_qty' => (int) $cycle->supply_plan_qty,
                'gap_qty' => (int) $cycle->supply_plan_qty - (int) $cycle->demand_plan_qty,
                'approved_by_user_id' => $approvedByUserId,
            ],
        ];

        if ($this->schemaRegistry !== null) {
            if (! $this->schemaRegistry->hasSchema(self::EVENT_SOP_PUBLISHED, 1)) {
                $this->schemaRegistry->registerSchema(
                    eventName: self::EVENT_SOP_PUBLISHED,
                    version: 1,
                    schemaDefinition: [
                        'type' => 'object',
                        'properties' => [
                            'cycle_code' => ['type' => 'string'],
                            'period' => ['type' => 'string'],
                            'demand_plan_qty' => ['type' => 'integer'],
                            'supply_plan_qty' => ['type' => 'integer'],
                            'approved_by_user_id' => ['type' => 'integer'],
                        ],
                        'required' => ['cycle_code', 'period', 'demand_plan_qty', 'supply_plan_qty', 'approved_by_user_id'],
                    ],
                    description: 'Publish an approved S&OP cycle plan (demand, supply, gaps) to manufacturing, procurement and distribution planners',
                    producerModule: 'ControlTower',
                    consumerModules: ['Manufacturing', 'Procurement', 'Distribution'],
                );
            }

            $this->schemaRegistry->validate(self::EVENT_SOP_PUBLISHED, 1, $payload);
        }

        if ($this->outboxBus !== null) {
            $this->outboxBus->record(
                eventType: self::EVENT_SOP_PUBLISHED,
                payload: $payload,
                idempotencyKey: $idempotencyKey,
                aggregate: $cycle,
                headers: [
                    'producer' => 'ControlTower',
                    'schema_version' => 1,
                ],
            );

            return;
        }

        Event::dispatch(self::EVENT_SOP_PUBLISHED, [$payload]);
    }

    private function resolveUser(User|int|null $user): ?User
    {
        if ($user instanceof User) {
            return $user;
        }

        if (is_int($user)) {
            return User::query()->find($user);
        }

        return null;
    }

    private function resolveCreatorForApproval(SopCycle $cycle): ?User
    {
        if ($cycle->created_by_user_id !== null) {
            $creator = User::query()->find((int) $cycle->created_by_user_id);
            if ($creator !== null) {
                return $creator;
            }
        }

        $firstActorId = SopEvent::query()
            ->where('sop_cycle_id', $cycle->id)
            ->whereNotNull('actor_user_id')
            ->orderBy('id')
            ->value('actor_user_id');

        return $firstActorId !== null ? User::query()->find((int) $firstActorId) : null;
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'demand_review' => 'Demand Review',
            'supply_review' => 'Supply Review',
            'pre_sop', 'pre_sop_balancing' => 'Pre-S&OP Balancing',
            default => $stage,
        };
    }

    private function truncateEventNote(string $note): string
    {
        return mb_substr(trim($note), 0, 255);
    }
}
