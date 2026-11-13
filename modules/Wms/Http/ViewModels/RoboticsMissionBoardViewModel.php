<?php

declare(strict_types=1);

namespace Modules\Wms\Http\ViewModels;

use Illuminate\Support\Collection;
use Modules\Wms\Domain\StateMachines\RobotMissionStateMachine;
use Modules\Wms\Models\RobotMission;

/**
 * ViewModel papan kendali misi robotika untuk Blade Control Tower (Wms::wms.robotics.*).
 */
final readonly class RoboticsMissionBoardViewModel
{
    /**
     * @param  Collection<int, RobotMission>  $missions
     * @return array{
     *     total:int,
     *     active_count:int,
     *     fallback_count:int,
     *     status_counts:array<string, int>,
     *     rows:list<array{id:int, mission_code:string, zone_code:string, status:string, status_badge:string, priority:int, retry_count:int, fallback_manual:bool, can_retry:bool}>
     * }
     */
    public function present(Collection $missions): array
    {
        $counts = [
            RobotMissionStateMachine::STATUS_QUEUED => 0,
            RobotMissionStateMachine::STATUS_ASSIGNED => 0,
            RobotMissionStateMachine::STATUS_MOVING => 0,
            RobotMissionStateMachine::STATUS_PICKING => 0,
            RobotMissionStateMachine::STATUS_DELIVERING => 0,
            RobotMissionStateMachine::STATUS_COMPLETED => 0,
            RobotMissionStateMachine::STATUS_FAILED => 0,
            RobotMissionStateMachine::STATUS_FALLBACK_MANUAL => 0,
        ];

        $activeCount = 0;
        $fallbackCount = 0;
        $rows = [];

        foreach ($missions as $mission) {
            $status = (string) $mission->status;
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            if (in_array($status, [
                RobotMissionStateMachine::STATUS_ASSIGNED,
                RobotMissionStateMachine::STATUS_MOVING,
                RobotMissionStateMachine::STATUS_PICKING,
                RobotMissionStateMachine::STATUS_DELIVERING,
            ], true)) {
                $activeCount++;
            }

            if ($status === RobotMissionStateMachine::STATUS_FALLBACK_MANUAL || (bool) $mission->fallback_manual) {
                $fallbackCount++;
            }

            $rows[] = [
                'id' => (int) $mission->id,
                'mission_code' => (string) $mission->mission_code,
                'zone_code' => (string) $mission->zone_code,
                'status' => $status,
                'status_badge' => self::badgeForStatus($status),
                'priority' => (int) $mission->priority,
                'retry_count' => (int) $mission->retry_count,
                'fallback_manual' => (bool) $mission->fallback_manual,
                'can_retry' => $status === RobotMissionStateMachine::STATUS_FAILED && (int) $mission->retry_count < RobotMissionStateMachine::MAX_RETRY_ATTEMPTS,
            ];
        }

        return [
            'total' => $missions->count(),
            'active_count' => $activeCount,
            'fallback_count' => $fallbackCount,
            'status_counts' => $counts,
            'rows' => $rows,
        ];
    }

    public static function badgeForStatus(string $status): string
    {
        return match ($status) {
            RobotMissionStateMachine::STATUS_COMPLETED => 'emerald',
            RobotMissionStateMachine::STATUS_MOVING,
            RobotMissionStateMachine::STATUS_PICKING,
            RobotMissionStateMachine::STATUS_DELIVERING => 'sky',
            RobotMissionStateMachine::STATUS_ASSIGNED => 'indigo',
            RobotMissionStateMachine::STATUS_FAILED => 'rose',
            RobotMissionStateMachine::STATUS_FALLBACK_MANUAL => 'amber',
            default => 'slate',
        };
    }
}
