<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Application\DTO\ScaleBenchmarkResultDTO;
use Modules\Core\Contracts\SimulationRunner;

/**
 * Runner benchmark beban skala (scale stress-test) untuk mengukur performa kernel simulasi.
 */
final class ScaleBenchmarkRunnerService
{
    public function __construct(
        private readonly SimulationRunner $runner,
        private readonly ScaleProvisioningEngine $scaleEngine,
    ) {}

    public function runBenchmark(string $tier = 'light', int $days = 10): ScaleBenchmarkResultDTO
    {
        $requirements = $this->scaleEngine->calculateRequirements($tier);

        $initialMemory = memory_get_usage();
        $start = microtime(true);

        $runName = "scale-bench-{$tier}-".uniqid();
        $runId = $this->runner->start(
            name: $runName,
            days: $days,
            seed: 777,
            mode: 'sandbox'
        );

        $elapsed = microtime(true) - $start;
        $peakMemory = memory_get_peak_usage() - $initialMemory;
        $daysPerSec = $elapsed > 0 ? ($days / $elapsed) : $days;

        return new ScaleBenchmarkResultDTO(
            tier: $tier,
            targetDays: $days,
            elapsedSeconds: $elapsed,
            daysPerSecond: $daysPerSec,
            peakMemoryBytes: max(0, $peakMemory),
            parameters: [
                'run_id' => $runId,
                'outbox_partitions' => $requirements['config']['outbox_partition_count'],
                'batch_size' => $requirements['recommended_batch_size'],
            ]
        );
    }
}
