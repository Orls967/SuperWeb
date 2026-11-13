<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Models\RobotUnit;

/**
 * Action untuk mengirim robot ke stasiun pengisian baterai dan memulihkan kesiapan armada.
 */
final class RechargeRobotUnitAction
{
    public function execute(int $robotUnitId, int $targetBatteryPct = 100): RobotUnit
    {
        return DB::transaction(static function () use ($robotUnitId, $targetBatteryPct): RobotUnit {
            $robot = RobotUnit::query()->lockForUpdate()->findOrFail($robotUnitId);
            $robot->update([
                'battery_pct' => max(0, min(100, $targetBatteryPct)),
                'status' => 'idle',
            ]);

            return $robot->fresh();
        });
    }
}
