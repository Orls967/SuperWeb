<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Modules\ControlTower\Application\Services\ControlTowerKpiDrilldownService;

final class ControlTowerKpiCommand extends Command
{
    protected $signature = 'tower:kpi {--from=} {--to=}';

    protected $description = 'Aggregate real OTIF/Fill-Rate permil KPIs from distribution orders and capture idempotent daily snapshot';

    public function handle(ControlTowerKpiDrilldownService $kpiService): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        try {
            $result = $kpiService->captureDailySnapshot(
                fromDate: is_string($from) && $from !== '' ? $from : null,
                toDate: is_string($to) && $to !== '' ? $to : null,
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $metrics = $result['metrics'];
        $snapshot = $result['snapshot'];

        $this->info(sprintf(
            'KPI [%s..%s] Snapshot #%d: OTIF=%d permil | FillRate=%d permil | Stockouts=%d | Alerts=%d | DOS=%d d | C2C=%d d',
            $result['period']['from'],
            $result['period']['to'],
            $snapshot->id,
            $metrics['otif_permil'],
            $metrics['fill_rate_permil'],
            $metrics['stockout_items_count'],
            $metrics['active_alerts_count'],
            $metrics['inventory_dos_days'],
            $metrics['cash_to_cash_days'],
        ));

        return self::SUCCESS;
    }
}
