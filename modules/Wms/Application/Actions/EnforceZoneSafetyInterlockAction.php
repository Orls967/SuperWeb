<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotZoneLock;

/**
 * Action untuk menegakkan safety interlock zona saat manusia terdeteksi di zona robot.
 */
final readonly class EnforceZoneSafetyInterlockAction
{
    public const int HUMAN_ZONE_SPEED_LIMIT_MM_S = 300;

    public const int DEFAULT_SPEED_LIMIT_MM_S = 1800;

    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function execute(
        string $warehouseId,
        string $zoneCode,
        bool $humanPresent,
    ): RobotZoneLock {
        return DB::transaction(function () use ($warehouseId, $zoneCode, $humanPresent): RobotZoneLock {
            $speedLimit = $humanPresent ? self::HUMAN_ZONE_SPEED_LIMIT_MM_S : self::DEFAULT_SPEED_LIMIT_MM_S;

            $lock = RobotZoneLock::query()
                ->where('warehouse_id', $warehouseId)
                ->where('zone_code', $zoneCode)
                ->where('lock_status', 'active')
                ->lockForUpdate()
                ->first();

            if ($lock === null) {
                $lock = RobotZoneLock::create([
                    'warehouse_id' => $warehouseId,
                    'zone_code' => $zoneCode,
                    'robot_unit_id' => null,
                    'human_present' => $humanPresent,
                    'interlock_active' => $humanPresent,
                    'speed_limit_mm_s' => $speedLimit,
                    'lock_status' => 'active',
                    'locked_at' => $this->clock->now(),
                ]);
            } else {
                $lock->update([
                    'human_present' => $humanPresent,
                    'interlock_active' => $humanPresent,
                    'speed_limit_mm_s' => $speedLimit,
                ]);
            }

            if ($humanPresent) {
                $activeMissions = RobotMission::query()
                    ->where('warehouse_id', $warehouseId)
                    ->where('zone_code', $zoneCode)
                    ->whereIn('status', [
                        RobotMissionStateMachine::STATE_EN_ROUTE,
                        RobotMissionStateMachine::STATE_PICKING,
                        RobotMissionStateMachine::STATE_DELIVERING,
                    ])
                    ->lockForUpdate()
                    ->get();

                foreach ($activeMissions as $mission) {
                    RobotMissionStateMachine::assertTransition(
                        $mission->status,
                        RobotMissionStateMachine::STATE_INTERLOCKED
                    );
                    $mission->update([
                        'status' => RobotMissionStateMachine::STATE_INTERLOCKED,
                    ]);
                }
            }

            return $lock->fresh();
        });
    }
}

