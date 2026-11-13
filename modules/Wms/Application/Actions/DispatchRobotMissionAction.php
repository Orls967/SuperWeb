<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotUnit;
use Modules\Wms\Models\RobotZoneLock;

/**
 * Action untuk menugaskan dan memberangkatkan misi robot ke zona target
 * dengan reservasi kunci zona eksklusif anti-tabrakan.
 */
final readonly class DispatchRobotMissionAction
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function execute(
        string $warehouseId,
        int $robotUnitId,
        string $zoneCode,
        string $missionCode,
        ?int $waveId = null,
        ?int $operatorUserId = null,
    ): RobotMission {
        return DB::transaction(function () use (
            $warehouseId,
            $robotUnitId,
            $zoneCode,
            $missionCode,
            $waveId,
            $operatorUserId
        ): RobotMission {
            $robot = RobotUnit::query()->lockForUpdate()->findOrFail($robotUnitId);

            if ($robot->warehouse_id !== $warehouseId) {
                throw new InvalidArgumentException('Robot berada di gudang yang berbeda.');
            }

            if ($robot->battery_pct < 15) {
                throw new InvalidArgumentException('Baterai robot terlalu rendah (<15%) untuk misi baru.');
            }

            $existingLock = RobotZoneLock::query()
                ->where('warehouse_id', $warehouseId)
                ->where('zone_code', $zoneCode)
                ->where('lock_status', 'active')
                ->lockForUpdate()
                ->first();

            if ($existingLock !== null && $existingLock->robot_unit_id !== $robotUnitId) {
                throw new InvalidArgumentException("Zona {$zoneCode} sedang dikunci oleh robot lain.");
            }

            if ($existingLock !== null && $existingLock->human_present) {
                throw new InvalidArgumentException("Zona {$zoneCode} sedang dalam safety interlock manusia.");
            }

            $now = $this->clock->now();

            if ($existingLock === null) {
                RobotZoneLock::create([
                    'warehouse_id' => $warehouseId,
                    'zone_code' => $zoneCode,
                    'robot_unit_id' => $robotUnitId,
                    'human_present' => false,
                    'interlock_active' => false,
                    'speed_limit_mm_s' => $robot->max_speed_mm_s,
                    'lock_status' => 'active',
                    'locked_at' => $now,
                ]);
            }

            RobotMissionStateMachine::assertTransition(
                RobotMissionStateMachine::STATUS_QUEUED,
                RobotMissionStateMachine::STATUS_ASSIGNED
            );
            RobotMissionStateMachine::assertTransition(
                RobotMissionStateMachine::STATUS_ASSIGNED,
                RobotMissionStateMachine::STATUS_MOVING
            );

            $robot->update([
                'status' => 'active',
                'current_zone_code' => $zoneCode,
            ]);

            return RobotMission::create([
                'mission_code' => $missionCode,
                'warehouse_id' => $warehouseId,
                'robot_unit_id' => $robotUnitId,
                'wave_id' => $waveId,
                'zone_code' => $zoneCode,
                'status' => RobotMissionStateMachine::STATUS_MOVING,
                'fallback_manual' => false,
                'assigned_operator_user_id' => $operatorUserId,
                'certificate_hash' => hash('sha256', "{$missionCode}|{$warehouseId}|{$robotUnitId}|{$zoneCode}"),
            ]);
        });
    }
}
