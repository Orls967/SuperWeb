<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use Modules\ControlTower\Application\Services\ControlTowerService;

class AuditControlTowerCommand extends Command
{
    protected $signature = 'tower:audit';

    protected $description = 'Audit supply chain control tower (multi-echelon stock, ATP/CTP invariant, and forecasts)';

    public function handle(ControlTowerService $service): int
    {
        $this->info('Starting Supply Chain Control Tower Audit...');

        $res = $service->auditControlTower();

        $this->line("Status: {$res['status']}");
        $this->line("Echelon Stocks: {$res['echelon_count']}");
        $this->line("Demand Forecasts: {$res['forecast_count']}");
        $this->line("Order Promises: {$res['promise_count']}");
        $this->line("Disruption Alerts: {$res['alert_count']}");
        $this->line("Discrepancies: {$res['discrepancy_count']}");

        if ($res['discrepancy_count'] > 0) {
            $this->error('Control tower audit FAILED: Invariant violation detected!');

            return self::FAILURE;
        }

        $this->info('Supply Chain Control Tower audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
