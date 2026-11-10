<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;

class SimDiffCommand extends Command
{
    protected $signature = 'sim:diff
        {a? : Nama atau ID sesi simulasi pertama}
        {b? : Nama atau ID sesi simulasi kedua}
        {--a= : Opsi nama atau ID sesi simulasi pertama}
        {--b= : Opsi nama atau ID sesi simulasi kedua}';

    protected $description = 'Membandingkan dua sesi simulasi deterministik dan mendeteksi hari simpangan pertama serta selisih per handler';

    public function handle(): int
    {
        $nameA = (string) ($this->option('a') ?: $this->argument('a'));
        $nameB = (string) ($this->option('b') ?: $this->argument('b'));

        $runA = SimRun::where('name', $nameA)->orWhere('id', $nameA)->first();
        $runB = SimRun::where('name', $nameB)->orWhere('id', $nameB)->first();

        if (! $runA || ! $runB) {
            $this->error("Salah satu atau kedua sesi simulasi tidak ditemukan: '{$nameA}', '{$nameB}'.");

            return self::FAILURE;
        }

        $this->info("Membandingkan sesi simulasi [{$runA->name}] (seed: {$runA->seed}) vs [{$runB->name}] (seed: {$runB->seed})...");

        // Bandingkan seed
        if ($runA->seed !== $runB->seed) {
            $this->warn("Divergence detected at Day 1: Seeds differ (Run A: {$runA->seed}, Run B: {$runB->seed}).");
        }

        // Ambil checkpoints kedua run
        $checkpointsA = SimCheckpoint::where('run_id', $runA->id)->orderBy('day_number', 'asc')->get()->keyBy('day_number');
        $checkpointsB = SimCheckpoint::where('run_id', $runB->id)->orderBy('day_number', 'asc')->get()->keyBy('day_number');

        $allDays = $checkpointsA->keys()->merge($checkpointsB->keys())->unique()->sort()->values();

        $divergenceFound = false;
        foreach ($allDays as $day) {
            $cpA = $checkpointsA->get($day);
            $cpB = $checkpointsB->get($day);

            if (! $cpA || ! $cpB) {
                $this->warn("Divergence detected at Day {$day}: Checkpoint keberadaan berbeda.");
                $divergenceFound = true;
                break;
            }

            if ($cpA->chain_hash !== $cpB->chain_hash) {
                $this->warn("Divergence detected at Day {$day}: Chain hash mismatch (A: {$cpA->chain_hash}, B: {$cpB->chain_hash}).");
                $this->displayHandlerDiff($cpA->handler_hashes ?? [], $cpB->handler_hashes ?? []);
                $divergenceFound = true;
                break;
            }
        }

        if (! $divergenceFound) {
            if ($runA->chain_hash === $runB->chain_hash && ! empty($runA->chain_hash)) {
                $this->info("Both simulation runs '{$runA->name}' and '{$runB->name}' are 100% identical across all days.");
            } else {
                $this->warn("Divergence detected at Day 1: Final chain hash mismatch (A: {$runA->chain_hash}, B: {$runB->chain_hash}).");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $hashesA
     * @param  array<string, mixed>  $hashesB
     */
    protected function displayHandlerDiff(array $hashesA, array $hashesB): void
    {
        $allKeys = array_unique(array_merge(array_keys($hashesA), array_keys($hashesB)));
        sort($allKeys);

        $this->table(
            ['Handler', 'Nilai Run A', 'Nilai Run B', 'Status'],
            array_map(function ($key) use ($hashesA, $hashesB) {
                $valA = json_encode($hashesA[$key] ?? 'N/A');
                $valB = json_encode($hashesB[$key] ?? 'N/A');
                $match = ($valA === $valB) ? 'IDENTIK' : 'BERBEDA';

                return [$key, $valA, $valB, $match];
            }, $allKeys)
        );
    }
}
