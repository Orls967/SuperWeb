<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;

class EgyDispatchHourCommand extends Command
{
    protected $signature = 'egy:dispatch-hour {hour? : Timestamp YYYY-MM-DD HH:00:00} {--load= : Target load in MW}';

    protected $description = 'Runs hourly merit-order grid dispatch and unit commitment';

    public function handle(EnergyGridAndMeteringService $service): int
    {
        $hour = $this->argument('hour') ?: CarbonImmutable::now()->format('Y-m-d H:00:00');
        $load = $this->option('load') ? (int) $this->option('load') : 950;
        $idempotencyKey = 'DISP-CMD-'.CarbonImmutable::parse($hour)->format('YmdH');

        $this->info("Executing hourly merit-order dispatch for {$hour} with target load {$load} MW...");

        $run = $service->runDispatch($hour, $load, $idempotencyKey);

        $this->info("Dispatch Run [{$run->run_number}]: Dispatched {$run->total_dispatched_mw} MW, Curtailed {$run->total_curtailed_mw} MW, Total Cost: Rp ".number_format($run->total_cost_thousand_idr * 1000));

        return Command::SUCCESS;
    }
}
