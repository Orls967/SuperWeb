<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;

class EgyShiftEvLoadCommand extends Command
{
    protected $signature = 'egy:shift-ev-load {site? : Site Asset ID} {--date= : Event date YYYY-MM-DD}';

    protected $description = 'Shifts flexible EV charging sessions from peak to off-peak hours and distributes demand response rewards';

    public function handle(EnergyGridAndMeteringService $service): int
    {
        $siteId = $this->argument('site') ? (int) $this->argument('site') : 101;
        $dateStr = $this->option('date') ?: CarbonImmutable::now()->toDateString();
        $date = CarbonImmutable::parse($dateStr);
        $idempotencyKey = "DR-CMD-{$siteId}-{$dateStr}";

        $this->info("Analyzing EV flexible loads for site {$siteId} on {$dateStr}...");

        $event = $service->shiftEvChargingLoad($siteId, $date, $idempotencyKey);

        $this->info("Demand Response Event [{$event->event_code}]: Shifted {$event->shifted_load_kwh} kWh, Savings: Rp ".number_format($event->cost_savings_idr).', Reward: Rp '.number_format($event->total_reward_idr));

        return Command::SUCCESS;
    }
}
