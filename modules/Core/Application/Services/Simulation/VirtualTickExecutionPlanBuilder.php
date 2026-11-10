<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Application\Services\SimTickRegistry;

/**
 * Penyusun rencana eksekusi tick harian (Execution Plan Builder).
 * Mengelompokkan handler ke dalam tahap independen (stages) untuk potensi paralelisasi masa depan.
 */
final class VirtualTickExecutionPlanBuilder
{
    public function __construct(
        private readonly SimTickRegistry $registry,
    ) {}

    /**
     * @return array{
     *     total_stages: int,
     *     stages: array<int, array<int, string>>,
     *     total_handlers: int
     * }
     */
    public function buildExecutionStages(): array
    {
        $handlers = $this->registry->getHandlers();
        $inDegree = [];
        $adj = [];
        $allNodes = [];

        foreach ($handlers as $h) {
            $name = $h->name();
            $allNodes[$name] = true;
            $inDegree[$name] = 0;
            $adj[$name] = [];
        }

        foreach ($handlers as $h) {
            $name = $h->name();
            foreach ($h->dependencies() as $dep) {
                if (isset($allNodes[$dep])) {
                    $adj[$dep][] = $name;
                    $inDegree[$name]++;
                }
            }
        }

        $stages = [];
        $currentStage = [];

        foreach ($inDegree as $node => $deg) {
            if ($deg === 0) {
                $currentStage[] = $node;
            }
        }

        while (! empty($currentStage)) {
            sort($currentStage);
            $stages[] = $currentStage;
            $nextStage = [];

            foreach ($currentStage as $u) {
                foreach ($adj[$u] as $v) {
                    $inDegree[$v]--;
                    if ($inDegree[$v] === 0) {
                        $nextStage[] = $v;
                    }
                }
            }

            $currentStage = $nextStage;
        }

        return [
            'total_stages' => count($stages),
            'stages' => $stages,
            'total_handlers' => count($handlers),
        ];
    }
}
