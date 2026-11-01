<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Illuminate\Console\Command;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;

class EgyPlanBatteryCommand extends Command
{
    protected $signature = 'egy:plan-battery {--capacity=100 : Battery capacity in kWh} {--power=50 : Battery power in kW}';

    protected $description = 'Plans battery energy storage system arbitrage schedule across TOU tariffs';

    public function handle(MicrogridAndResilienceService $service): int
    {
        $battery = [
            'capacity_kwh' => (int) $this->option('capacity'),
            'max_power_kw' => (int) $this->option('power'),
            'soc_step_kwh' => 10,
            'efficiency_bps' => 9500,
        ];

        $prices = [1000, 1000, 1450, 1450, 2100, 2100, 1450, 1000];

        $plan = $service->planBatteryArbitrage($battery, $prices);

        $this->info('BESS Arbitrage planned. Estimated Net Profit: Rp '.number_format($plan['profit_minor']));
        $this->info('Schedule Actions: '.implode(' -> ', $plan['actions']));

        return Command::SUCCESS;
    }
}
