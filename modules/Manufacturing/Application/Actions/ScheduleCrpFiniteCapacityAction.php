<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Actions;

use InvalidArgumentException;
use Modules\Manufacturing\Domain\Models\CapacityLoad;
use Modules\Manufacturing\Domain\Models\MrpRun;
use Modules\Manufacturing\Domain\Models\WorkCenter;
use Modules\Shared\Application\BaseAction;

final class ScheduleCrpFiniteCapacityAction extends BaseAction
{
    /**
     * Calculates CRP finite capacity load for work centers across given period and flags bottlenecks.
     *
     * @param  list<array{work_center_id: string, required_minutes: int}>  $operations
     * @return list<CapacityLoad>
     */
    public function execute(MrpRun $run, string $periodStart, array $operations): array
    {
        if (empty($operations)) {
            throw new InvalidArgumentException('Daftar operasi work center CRP tidak boleh kosong.');
        }

        return $this->transaction(function () use ($run, $periodStart, $operations) {
            $createdLoads = [];

            // Group required minutes by work center
            $aggregatedLoad = [];
            foreach ($operations as $op) {
                $wcId = (string) $op['work_center_id'];
                $aggregatedLoad[$wcId] = ($aggregatedLoad[$wcId] ?? 0) + (int) $op['required_minutes'];
            }

            foreach ($aggregatedLoad as $workCenterId => $loadMinutes) {
                $workCenter = WorkCenter::findOrFail($workCenterId);
                // Daily capacity: standard 8 hours = 480 minutes adjusted by efficiency
                $nominalMinutes = 480;
                $efficiency = max(1, (int) $workCenter->efficiency_percent);
                $availableMinutes = (int) intdiv($nominalMinutes * $efficiency, 100);

                $utilization = $availableMinutes > 0
                    ? round(($loadMinutes / $availableMinutes) * 100, 2)
                    : 100.0;

                $isBottleneck = $utilization > 100.0;

                $load = CapacityLoad::create([
                    'run_id' => $run->id,
                    'work_center_id' => $workCenter->id,
                    'period_start' => $periodStart,
                    'load_minutes' => $loadMinutes,
                    'capacity_minutes' => $availableMinutes,
                    'utilization_percent' => $utilization,
                    'bottleneck' => $isBottleneck,
                ]);

                $createdLoads[] = $load;
            }

            $this->audit('mfg.crp_scheduled', $run, null, [
                'period_start' => $periodStart,
                'work_centers_scheduled' => count($createdLoads),
            ]);

            return $createdLoads;
        });
    }
}
