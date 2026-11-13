<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Actions\AllocateRobotMissionsAction;
use Modules\Wms\Domain\Models\Warehouse;

/**
 * Command terjadwal 5-menitan idempoten untuk alokasi misi robot memakai HungarianSolver (D279-3, D279-9).
 */
final class RoboticsAllocateCommand extends Command
{
    protected $signature = 'robotics:allocate {--warehouse= : ID gudang opsional} {--window=default : Kunci jendela idempoten}';

    protected $description = 'Alokasikan misi robot queued ke armada online secara deterministik (HungarianSolver)';

    public function handle(AllocateRobotMissionsAction $action): int
    {
        $whId = $this->option('warehouse');
        $windowKey = (string) $this->option('window');

        $warehouses = $whId !== null
            ? Warehouse::query()->where('id', $whId)->get()
            : Warehouse::query()->orderBy('code')->get();

        $totalAllocated = 0;
        foreach ($warehouses as $warehouse) {
            $res = $action->execute((string) $warehouse->id, $windowKey);
            $totalAllocated += count($res['allocated']);
            $this->line(sprintf('WH %s: allocated=%d unassigned=%d total_cost=%d', $warehouse->code, count($res['allocated']), count($res['unassigned_mission_ids']), $res['total_cost']));
        }

        $this->info("robotics:allocate selesai (allocated={$totalAllocated}).");

        return self::SUCCESS;
    }
}
