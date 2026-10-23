<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Observability\PrometheusMetricsCollector;
use Illuminate\Console\Command;

/**
 * Artisan command to export Prometheus exposition format metrics across 46 modules (Tahap 12).
 */
final class PrometheusMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:prometheus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export real-time platform observability metrics across 46 modules in standard Prometheus Exposition Format';

    /**
     * Execute the console command.
     */
    public function handle(PrometheusMetricsCollector $collector): int
    {
        $output = $collector->render();
        $this->output->write($output."\n");

        return self::SUCCESS;
    }
}
