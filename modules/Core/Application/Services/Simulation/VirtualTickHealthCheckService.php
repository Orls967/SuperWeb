<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Application\Services\SimTickRegistry;
use Modules\Core\Domain\Algorithms\Simulation\Verifiers\TopologicalGraphCycleDetector;

/**
 * Layanan diagnosis kesehatan ekosistem Tick Handler simulasi.
 */
final class VirtualTickHealthCheckService
{
    public function __construct(
        private readonly SimTickRegistry $registry,
        private readonly TopologicalGraphCycleDetector $cycleDetector,
    ) {}

    /**
     * @return array{
     *     total_handlers: int,
     *     has_dependency_cycles: bool,
     *     cycle_path: array<int, string>,
     *     registered_names: array<int, string>,
     *     status: string
     * }
     */
    public function runDiagnostics(): array
    {
        $handlers = $this->registry->getHandlers();
        $graph = [];

        foreach ($handlers as $h) {
            $graph[$h->name()] = $h->dependencies();
        }

        $cycleResult = $this->cycleDetector->detectCycle($graph);

        return [
            'total_handlers' => count($handlers),
            'has_dependency_cycles' => $cycleResult['has_cycle'],
            'cycle_path' => $cycleResult['cycle_path'],
            'registered_names' => array_keys($handlers),
            'status' => $cycleResult['has_cycle'] ? 'UNHEALTHY_CYCLE_DETECTED' : 'HEALTHY',
        ];
    }
}
