<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotUnit;
use Modules\Wms\Models\RobotZoneLock;

/**
 * Action untuk mengalihkan misi robot yang gagal/terhenti menjadi fallback manual ke operator.
 */
final readonly class TriggerRobotFallbackAction
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function execute(
        int $missionId,
        int $operatorUserId,
        string $reason = 'robot_fault_manual_takeover',
    ): RobotMission {
        return DB::transaction(function () use ($missionId, $operatorUserId, $reason): RobotMission {
            $mission = RobotMission::query()->lockForUpdate()->findOrFail($missionId);

            RobotMissionStateMachine::assertTransition(
                $mission->status,
                RobotMissionStateMachine::STATE_FAILED_FALLBACK
            );

            $mission->update([
                'status' => RobotMissionStateMachine::STATE_FAILED_FALLBACK,
                'fallback_manual' => true,
                'assigned_operator_user_id' => $operatorUserId,
                'failure_reason' => $reason,
            ]);

            RobotUnit::query()
                ->where('id', $mission->robot_unit_id)
                ->update(['status' => 'faulted']);

            RobotZoneLock::query()
                ->where('warehouse_id', $mission->warehouse_id)
                ->where('zone_code', $mission->zone_code)
                ->where('robot_unit_id', $mission->robot_unit_id)
                ->where('lock_status', 'active')
                ->update([
                    'lock_status' => 'released',
                    'released_at' => $this->clock->now(),
                ]);

            return $mission->fresh();
        });
    }
}
