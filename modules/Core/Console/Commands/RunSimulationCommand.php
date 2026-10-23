<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Core\Application\Services\SimTickRegistry;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Domain\Models\SimRun;

class RunSimulationCommand extends Command
{
    protected $signature = 'sim:run
        {--days=1 : Number of days to compress/advance}
        {--seed=42 : Deterministic seed}
        {--name=default : Run session name}
        {--resume : Resume simulation from last saved checkpoint}';

    protected $description = 'Run or advance the deterministic Simulation Kernel virtual clock with daily tick handlers and state checkpoints';

    public function handle(SimClockInterface $simClock, SimTickRegistry $tickRegistry): int
    {
        $days = max(1, (int) $this->option('days'));
        $seed = (int) $this->option('seed');
        $name = (string) $this->option('name');
        $resume = (bool) $this->option('resume');

        mt_srand($seed);
        srand($seed);

        $existingRun = SimRun::where('name', $name)->first();

        if ($resume && $existingRun !== null) {
            $startDay = ((int) $existingRun->last_completed_day) + 1;
            $startVirtualNow = Carbon::parse($existingRun->virtual_now);
            $seed = (int) $existingRun->seed;
            $run = $simClock->initRun($name, $startVirtualNow, $seed);
            $this->info("Simulation Run [{$name}] resumed from day {$startDay} at {$startVirtualNow->toIso8601String()} (seed: {$seed})");
        } else {
            $startDay = 1;
            $startVirtualNow = Carbon::parse('2026-01-01 00:00:00');
            $run = $simClock->initRun($name, $startVirtualNow, $seed);
            $run->last_completed_day = 0;
            $run->last_state_hash = null;
            $run->save();
            $this->info("Simulation Run [{$name}] initialized at {$startVirtualNow->toIso8601String()} (seed: {$seed})");
        }

        $lastHash = $run->last_state_hash;
        $runner = fn (string $command, array $arguments = []): int => $this->callSilent($command, $arguments);

        for ($d = $startDay; $d < $startDay + $days; $d++) {
            $currentNow = $simClock->advanceDays(1);
            $tickResults = $tickRegistry->executeDay($currentNow, $d, $runner);

            $snapshot = [
                'day' => $d,
                'seed' => $seed,
                'virtual_now' => $currentNow->toIso8601String(),
                'ticks' => $tickResults,
                'prev_hash' => $lastHash,
            ];

            $lastHash = hash('sha256', (string) json_encode($snapshot));

            $run->virtual_now = $currentNow;
            $run->last_completed_day = $d;
            $run->last_state_hash = $lastHash;
            $run->metrics = array_merge($run->metrics ?? [], [
                'last_hash' => $lastHash,
                'last_day' => $d,
            ]);
            $run->save();

            $this->line("Day {$d} ({$currentNow->toDateString()}): State Hash = {$lastHash}");
        }

        $this->info("Advanced {$days} days. Virtual now: {$run->virtual_now->toIso8601String()}");
        $this->info("Simulation complete. Total days simulated: {$run->last_completed_day}. Final state hash: {$lastHash}");

        return self::SUCCESS;
    }
}
