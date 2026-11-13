<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Services\RoboticsMissionPriorityPolicy;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotMissionEvent;

/**
 * Memindai misi robotika yang mendekati atau melewati SLA antrean, menaikkan prioritas efektif,
 * mencatat peristiwa append-only, serta mengirim eskalasi ke TaskInbox.
 */
final readonly class EscalateOverdueMissionsAction
{
    public function __construct(
        private RoboticsMissionPriorityPolicy $priorityPolicy,
        private TaskInboxService $taskInbox,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{warehouse_id:string, scanned:int, escalated_count:int, escalated_mission_codes:list<string>}
     */
    public function execute(string $warehouseId, int $slaMinutes = RoboticsMissionPriorityPolicy::SLA_BREACH_AGE_MINUTES): array
    {
        return DB::transaction(function () use ($warehouseId, $slaMinutes): array {
            $now = $this->clock->now();
            $missions = RobotMission::query()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('status', [
                    RobotMissionStateMachine::STATUS_QUEUED,
                    RobotMissionStateMachine::STATUS_ASSIGNED,
                    RobotMissionStateMachine::STATUS_MOVING,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $escalatedCodes = [];
            foreach ($missions as $mission) {
                $createdAt = $mission->created_at ?? $now;
                $ageMinutes = (int) $createdAt->diffInMinutes($now);
                if (! $this->priorityPolicy->shouldEscalate((string) $mission->status, max($ageMinutes, $slaMinutes === 0 ? 1 : 0), (int) $mission->retry_count)) {
                    if ($ageMinutes < $slaMinutes) {
                        continue;
                    }
                }

                $newPriority = $this->priorityPolicy->computeEffectivePriority(
                    basePriority: (int) $mission->priority,
                    queueAgeMinutes: $ageMinutes,
                    retryCount: (int) $mission->retry_count,
                    carrierAtRisk: true,
                );

                if ($newPriority > (int) $mission->priority) {
                    $mission->update(['priority' => $newPriority]);
                }

                RobotMissionEvent::query()->create([
                    'mission_id' => $mission->id,
                    'warehouse_id' => $warehouseId,
                    'from_status' => (string) $mission->status,
                    'to_status' => (string) $mission->status,
                    'reason_code' => 'SLA_PRIORITY_ESCALATION',
                    'reason_note' => "Prioritas dinaikkan ke {$newPriority} (usia antrean {$ageMinutes} menit).",
                    'metadata' => ['priority' => $newPriority, 'age_minutes' => $ageMinutes],
                    'recorded_at' => $now,
                ]);

                $this->taskInbox->createTask([
                    'title' => "Eskalasi SLA Misi Robot {$mission->mission_code}",
                    'description' => "Misi {$mission->mission_code} di zona {$mission->zone_code} melewati ambang SLA ({$ageMinutes} menit).",
                    'module' => 'Wms',
                    'source_entity_type' => 'robot_mission',
                    'source_entity_id' => (string) $mission->id,
                    'assignee_role' => 'hub_operator',
                    'priority' => 'high',
                ]);

                $escalatedCodes[] = (string) $mission->mission_code;
            }

            return [
                'warehouse_id' => $warehouseId,
                'scanned' => $missions->count(),
                'escalated_count' => count($escalatedCodes),
                'escalated_mission_codes' => $escalatedCodes,
            ];
        });
    }
}
