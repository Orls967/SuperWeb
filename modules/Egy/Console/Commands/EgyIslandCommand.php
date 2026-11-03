<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Illuminate\Console\Command;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;

class EgyIslandCommand extends Command
{
    protected $signature = 'egy:island {--capacity=500 : Available islanding capacity in kW}';

    protected $description = 'Executes priority knapsack microgrid islanding emergency dispatch';

    public function handle(MicrogridAndResilienceService $service): int
    {
        $capacity = (int) $this->option('capacity');

        $groups = [
            'HOSPITAL' => [['id' => 'ICU_01', 'kw' => 150], ['id' => 'VENT_01', 'kw' => 100]],
            'FACTORY' => [['id' => 'FURNACE_01', 'kw' => 120], ['id' => 'LINE_01', 'kw' => 80]],
            'MALL' => [['id' => 'CHILLER_01', 'kw' => 90], ['id' => 'LIGHT_01', 'kw' => 40]],
        ];

        $plan = $service->planIslanding($capacity, $groups);

        $this->info("Islanding Dispatch executed with capacity {$capacity} kW:");
        $this->info("- Served: {$plan['total_served_kw']} kW (".implode(', ', $plan['served_loads']).')');
        $this->info('- Shed: '.implode(', ', $plan['shed_loads']));

        return Command::SUCCESS;
    }
}
