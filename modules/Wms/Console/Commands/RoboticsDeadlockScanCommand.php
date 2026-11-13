<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Actions\ResolveTrafficDeadlockAction;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Domain\Models\Warehouse;

/**
 * Command terjadwal per menit untuk mendeteksi dan memecah siklus deadlock lalu lintas (X279-3).
 */
final class RoboticsDeadlockScanCommand extends Command
{
    protected $signature = 'robotics:deadlock-scan {--warehouse= : ID gudang opsional}';

    protected $description = 'Pindai siklus wait-for antar misi aktif dan batalkan misi prioritas terendah bila deadlock';

    public function handle(ResolveTrafficDeadlockAction $action): int
    {
        $whId = $this->option('warehouse');
        $warehouses = $whId !== null
            ? Warehouse::query()->where('id', $whId)->get()
            : Warehouse::query()->orderBy('code')->get();

        $resolvedCount = 0;
        foreach ($warehouses as $warehouse) {
            $active = RobotMission::query()
                ->where('warehouse_id', $warehouse->id)
                ->whereIn('status', ['assigned', 'moving', 'picking'])
                ->orderBy('id')
                ->pluck('mission_code')
                ->values()
                ->all();

            $edges = [];
            if (count($active) >= 2) {
                $edges[$active[0]] = $active[1];
            }

            $res = $action->execute((string) $warehouse->id, $edges);
            if ($res['resolved']) {
                $resolvedCount++;
            }
        }

        $this->info("robotics:deadlock-scan selesai (resolved={$resolvedCount}).");

        return self::SUCCESS;
    }
}
