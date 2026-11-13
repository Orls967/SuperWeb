<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotMissionEvent;
use Modules\Wms\Models\Task as WarehouseTask;
use Modules\Wms\Models\TrafficZone;
use Modules\Wms\Models\ZoneReservation;

/**
 * Orkestrator transisi state machine RobotMission (8 status) dengan guard, kompensasi reservasi,
 * konfirmasi WarehouseTask Fase 41, riwayat append-only, TaskInbox, dan Outbox event (D279-1, D279-8).
 */
final class RobotMissionTransitionService
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly OutboxBusInterface $outboxBus,
        private readonly TaskInboxService $taskInbox,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function transition(
        RobotMission $mission,
        string $toStatus,
        ?int $actorUserId = null,
        ?string $reasonCode = null,
        ?string $reasonNote = null,
        array $context = [],
    ): RobotMission {
        return DB::transaction(function () use ($mission, $toStatus, $actorUserId, $reasonCode, $reasonNote, $context): RobotMission {
            /** @var RobotMission $locked */
            $locked = RobotMission::query()->lockForUpdate()->findOrFail($mission->id);
            $fromStatus = (string) $locked->status;

            $enrichedContext = $this->enrichContext($locked, $toStatus, $reasonCode, $context);
            RobotMissionStateMachine::assertTransitionWithGuards($locked, $toStatus, $enrichedContext);

            $now = $this->clock->now();

            // Kompensasi otomatis: pelepasan reservasi zona aktif saat failed atau completed
            if (in_array($toStatus, [RobotMissionStateMachine::STATUS_FAILED, RobotMissionStateMachine::STATUS_COMPLETED], true)) {
                ZoneReservation::query()
                    ->where('mission_id', $locked->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'released',
                        'released_at' => $now,
                        'release_reason' => $toStatus === RobotMissionStateMachine::STATUS_FAILED
                            ? 'compensation_mission_failed:'.($reasonCode ?? 'UNKNOWN')
                            : 'mission_completed',
                    ]);
            }

            // Sinkronkan WarehouseTask Fase 41 jika misi selesai
            if ($toStatus === RobotMissionStateMachine::STATUS_COMPLETED && $locked->warehouse_task_id !== null) {
                WarehouseTask::query()
                    ->where('id', $locked->warehouse_task_id)
                    ->where('status', '!=', 'confirmed')
                    ->update(['status' => 'confirmed']);
            }

            if ($toStatus === RobotMissionStateMachine::STATUS_QUEUED && $fromStatus === RobotMissionStateMachine::STATUS_FAILED) {
                $locked->retry_count = (int) $locked->retry_count + 1;
            }

            if ($toStatus === RobotMissionStateMachine::STATUS_FALLBACK_MANUAL) {
                $locked->fallback_manual = true;
            }

            if ($reasonCode !== null && $toStatus === RobotMissionStateMachine::STATUS_FAILED) {
                $locked->failure_reason = $reasonCode;
            }

            $locked->status = $toStatus;
            $locked->save();

            RobotMissionEvent::query()->create([
                'mission_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_user_id' => $actorUserId,
                'reason_code' => $reasonCode,
                'reason_note' => $reasonNote,
                'metadata' => [
                    'retry_count' => $locked->retry_count,
                    'fallback_manual' => $locked->fallback_manual,
                    'zone_code' => $locked->zone_code,
                ],
                'recorded_at' => $now,
            ]);

            if ($toStatus === RobotMissionStateMachine::STATUS_FALLBACK_MANUAL) {
                $this->taskInbox->createTask([
                    'title' => "Fallback Manual Misi Robot {$locked->mission_code}",
                    'description' => "Misi {$locked->mission_code} di zona {$locked->zone_code} beralih ke fallback manual ({$reasonCode}).",
                    'module' => 'Wms',
                    'entity_type' => 'robot_mission',
                    'entity_id' => (string) $locked->id,
                    'priority' => 'high',
                ]);
            }

            $this->publishLifecycleEventAfterCommit($locked, $fromStatus, $toStatus, $reasonCode);

            return $locked->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function enrichContext(RobotMission $mission, string $toStatus, ?string $reasonCode, array $context): array
    {
        if ($reasonCode !== null && ! isset($context['reason_code'])) {
            $context['reason_code'] = $reasonCode;
        }

        if ($toStatus === RobotMissionStateMachine::STATUS_ASSIGNED && $mission->robot_id !== null) {
            /** @var Robot|null $robot */
            $robot = Robot::query()->find($mission->robot_id);
            if ($robot !== null) {
                $context['robot_online'] ??= (bool) $robot->is_online;
                $context['robot_battery_pct'] ??= (int) $robot->battery_pct;
            }
        }

        if ($toStatus === RobotMissionStateMachine::STATUS_MOVING) {
            $hasReservation = ZoneReservation::query()
                ->where('mission_id', $mission->id)
                ->where('status', 'active')
                ->exists();
            $context['has_active_zone_reservation'] ??= $hasReservation;

            if ($mission->traffic_zone_id !== null) {
                /** @var TrafficZone|null $zone */
                $zone = TrafficZone::query()->find($mission->traffic_zone_id);
                if ($zone !== null) {
                    $context['zone_staff_blocked'] ??= (bool) $zone->staff_blocked;
                    $context['interlock_passed'] ??= ! (bool) $zone->staff_blocked;
                }
            }
        }

        if ($toStatus === RobotMissionStateMachine::STATUS_COMPLETED && $mission->warehouse_task_id !== null) {
            $task = WarehouseTask::query()->find($mission->warehouse_task_id);
            if ($task !== null && $mission->status === RobotMissionStateMachine::STATUS_FALLBACK_MANUAL) {
                $context['warehouse_task_confirmed'] ??= in_array((string) $task->status, ['confirmed', 'closed'], true);
            }
        }

        return $context;
    }

    private function publishLifecycleEventAfterCommit(
        RobotMission $mission,
        string $fromStatus,
        string $toStatus,
        ?string $reasonCode,
    ): void {
        if ($toStatus === RobotMissionStateMachine::STATUS_COMPLETED) {
            $eventName = 'robotics.mission.completed.v1';
        } elseif ($toStatus === RobotMissionStateMachine::STATUS_FAILED) {
            $eventName = 'robotics.mission.failed.v1';
        } else {
            return;
        }

        $nowIso = $this->clock->now()->toIso8601String();
        $payload = [
            'event_id' => "wms-robotics-{$mission->id}-{$toStatus}-{$mission->retry_count}",
            'occurred_at' => $nowIso,
            'warehouse_id' => (string) $mission->warehouse_id,
            'mission_id' => $mission->id,
            'mission_code' => $mission->mission_code,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason_code' => $reasonCode,
        ];

        DB::afterCommit(function () use ($eventName, $mission, $payload): void {
            $this->outboxBus->publish($eventName, $payload, "robotics-mission-{$mission->id}-{$payload['to_status']}-{$mission->retry_count}");
        });
    }
}
