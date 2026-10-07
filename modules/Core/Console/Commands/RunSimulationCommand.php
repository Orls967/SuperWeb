<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Core\Contracts\SimClockInterface;

class RunSimulationCommand extends Command
{
    protected $signature = 'sim:run {--days=1 : Number of days to compress/advance} {--seed=42 : Deterministic seed} {--name=default : Run session name}';

    protected $description = 'Run or advance the deterministic Simulation Kernel virtual clock';

    public function handle(SimClockInterface $simClock): int
    {
        $days = (int) $this->option('days');
        $seed = (int) $this->option('seed');
        $name = (string) $this->option('name');

        mt_srand($seed);
        srand($seed);

        $start = Carbon::parse('2026-01-01 00:00:00');
        $run = $simClock->initRun($name, $start, $seed);

        $this->info("Simulation Run [{$run->name}] initialized at {$run->sim_start_at->toIso8601String()} (seed: {$seed})");

        $newNow = $simClock->advanceDays($days);

        $this->info("Advanced {$days} days. Virtual now: {$newNow->toIso8601String()}");

        return self::SUCCESS;
    }
}
