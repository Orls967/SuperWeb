<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Actions\DispatchRobotMissionAction;
use Modules\Wms\Application\Services\RoboticsMissionLifecycleSaga;
use Modules\Wms\Models\TrafficZone;

/**
 * Perintah CLI untuk memberangkatkan misi robotik ke zona gudang target.
 */
class RoboticsDispatchCommand extends Command
{
    protected $signature = 'wms:robotics-dispatch {warehouse_id} {robot_unit_id} {zone_code} {--mission=} {--saga : Eksekusi siklus SAGA penuh dengan kompensasi zona}';

    protected $description = 'Dispatch misi robotik gudang ke zona target dengan kunci zona eksklusif';

    public function handle(DispatchRobotMissionAction $action, RoboticsMissionLifecycleSaga $saga): int
    {
        $warehouseId = (string) $this->argument('warehouse_id');
        $robotUnitId = (int) $this->argument('robot_unit_id');
        $zoneCode = (string) $this->argument('zone_code');
        $missionCode = (string) ($this->option('mission') ?: 'MIS-'.strtoupper(substr(md5($warehouseId.$robotUnitId.$zoneCode), 0, 8)));

        $mission = $action->execute(
            warehouseId: $warehouseId,
            robotUnitId: $robotUnitId,
            zoneCode: $zoneCode,
            missionCode: $missionCode,
        );

        if ((bool) $this->option('saga')) {
            $zone = TrafficZone::query()
                ->where('warehouse_id', $warehouseId)
                ->where('zone_code', $zoneCode)
                ->first();

            if ($zone !== null) {
                $sagaRes = $saga->execute($mission, $zone, 0, 5, 1);
                $this->info("SAGA misi {$sagaRes['mission']->mission_code}: outcome={$sagaRes['outcome']}.");

                return self::SUCCESS;
            }
        }

        $this->info("✓ Misi {$mission->mission_code} diberangkatkan (status: {$mission->status}).");

        return self::SUCCESS;
    }
}
