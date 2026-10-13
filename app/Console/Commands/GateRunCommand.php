<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Gate\GateRunner;
use Illuminate\Console\Command;

/**
 * Artisan command to execute full quality gate pipeline (PROGRESS R0.2, P4).
 */
final class GateRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gate:run {--manifest= : Path to output manifest file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs all 9 mandatory gate pipeline steps and records genuine results to gate manifest';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $manifestPath = $this->option('manifest');
        $runner = new GateRunner(base_path());

        $this->info('Starting full quality gate pipeline (9 mandatory steps)...');

        $exitCode = $runner->run(
            manifestPath: is_string($manifestPath) ? $manifestPath : null,
            logger: function (string $type, string $buffer): void {
                if ($type === 'info') {
                    $this->info($buffer);
                } elseif ($type === 'error') {
                    $this->error($buffer);
                } else {
                    $this->output->write($buffer);
                }
            }
        );

        if ($exitCode === 0) {
            $this->info('Quality gate PASSED: All 9 steps succeeded.');
        } else {
            $this->error("Quality gate FAILED with exit code {$exitCode}.");
        }

        return $exitCode;
    }
}
