<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Domain\Algorithms\ChargingSchedulePlanner;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotChargeSchedule;

/**
 * Action penjadwalan pengisian daya armada robot dengan syarat minimum armada siaga (X279-1).
 */
final readonly class PlanRobotChargingScheduleAction
{
    public function __construct(
        private ChargingSchedulePlanner $planner,
    ) {}

    /**
     * @return array{schedules: list<RobotChargeSchedule>, deferred_robot_ids: list<int>, max_concurrent: int}
     */
    public function execute(
        string $warehouseId,
        int $lowBatteryThresholdPct = 35,
        int $minFleetAvailable = 2,
        int $chargeWindowStartMin = 0,
        int $slotDurationMin = 30,
        int $horizonSlots = 4,
    ): array {
        return DB::transaction(function () use (
            $warehouseId,
            $lowBatteryThresholdPct,
            $minFleetAvailable,
            $chargeWindowStartMin,
            $slotDurationMin,
            $horizonSlots,
        ): array {
            $robots = Robot::query()
                ->where('warehouse_id', $warehouseId)
                ->orderBy('id')
                ->get()
                ->map(static fn (Robot $r): array => [
                    'robot_id' => (int) $r->id,
                    'robot_code' => (string) $r->robot_code,
                    'battery_pct' => (int) $r->battery_pct,
                    'is_online' => (bool) $r->is_online,
                ])
                ->all();

            $plan = $this->planner->plan(
                $robots,
                $lowBatteryThresholdPct,
                $minFleetAvailable,
                $chargeWindowStartMin,
                $slotDurationMin,
                $horizonSlots,
            );

            $saved = [];
            foreach ($plan['schedules'] as $item) {
                $idemKey = "chg:{$warehouseId}:{$item['robot_id']}:{$item['window_start_min']}";
                $saved[] = RobotChargeSchedule::query()->updateOrCreate(
                    ['idempotency_key' => $idemKey],
                    [
                        'warehouse_id' => $warehouseId,
                        'robot_id' => $item['robot_id'],
                        'window_start_min' => $item['window_start_min'],
                        'window_end_min' => $item['window_end_min'],
                        'initial_battery_pct' => $item['initial_battery_pct'],
                        'target_battery_pct' => $item['target_battery_pct'],
                        'min_fleet_available' => $minFleetAvailable,
                        'status' => 'scheduled',
                    ],
                );
            }

            return [
                'schedules' => $saved,
                'deferred_robot_ids' => $plan['deferred_robot_ids'],
                'max_concurrent' => $plan['max_concurrent_charging'],
            ];
        });
    }
}
