<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Application\Actions\IngestSimulatedTelemetryBatchAction;
use Modules\Wms\Application\Actions\PlanRobotChargingScheduleAction;
use Modules\Wms\Application\Actions\RechargeRobotUnitAction;
use Modules\Wms\Domain\Models\Warehouse;

/**
 * Command terjadwal per jam untuk menyusun jadwal pengisian daya robot (X279-1).
 */
final class RoboticsChargePlanCommand extends Command
{
    protected $signature = 'robotics:charge-plan {--warehouse= : ID gudang opsional} {--threshold=35 : Ambang baterai %} {--min-fleet=2 : Minimum armada siaga} {--ingest-telemetry : Kumpulkan batch telemetri sebelum perencanaan} {--recharge-unit= : ID RobotUnit untuk pengisian baterai}';

    protected $description = 'Jadwalkan pengisian daya robot baterai rendah dengan menjaga minimum armada siaga';

    public function handle(
        PlanRobotChargingScheduleAction $action,
        IngestSimulatedTelemetryBatchAction $telemetryAction,
        RechargeRobotUnitAction $rechargeAction,
        ClockInterface $clock,
    ): int {
        $whId = $this->option('warehouse');
        $threshold = (int) $this->option('threshold');
        $minFleet = (int) $this->option('min-fleet');
        $ingestTelemetry = (bool) $this->option('ingest-telemetry');
        $rechargeUnitId = $this->option('recharge-unit');

        if ($rechargeUnitId !== null) {
            $unit = $rechargeAction->execute((int) $rechargeUnitId, 100);
            $this->line("Recharge unit {$unit->robot_code} selesai (battery={$unit->battery_pct}%).");
        }

        $warehouses = $whId !== null
            ? Warehouse::query()->where('id', $whId)->get()
            : Warehouse::query()->orderBy('code')->get();

        $totalScheduled = 0;
        $tickKey = $clock->now()->format('YmdH');
        foreach ($warehouses as $warehouse) {
            if ($ingestTelemetry) {
                $telemetryAction->execute((string) $warehouse->id, $tickKey);
            }
            $res = $action->execute((string) $warehouse->id, $threshold, $minFleet);
            $totalScheduled += count($res['schedules']);
            $this->line(sprintf('WH %s: scheduled=%d deferred=%d', $warehouse->code, count($res['schedules']), count($res['deferred_robot_ids'])));
        }

        $this->info("robotics:charge-plan selesai (scheduled={$totalScheduled}).");

        return self::SUCCESS;
    }
}
