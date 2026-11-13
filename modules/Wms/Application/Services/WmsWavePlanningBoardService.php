<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Domain\Algorithms\WaveBatchOptimizer;
use Modules\Wms\Domain\Services\LaborProductivityEstimator;
use Modules\Wms\Domain\Services\PickWavePriorityScorer;

/**
 * Menyusun papan perencanaan wave picking beserta estimasi kebutuhan tenaga kerja operator.
 */
final class WmsWavePlanningBoardService
{
    public function __construct(
        private readonly PickWavePriorityScorer $scorer,
        private readonly WaveBatchOptimizer $batchOptimizer,
        private readonly LaborProductivityEstimator $laborEstimator,
    ) {}

    /**
     * @param  list<array{order_id: string, minutes_to_cutoff: int, is_express: bool, line_count: int, age_hours: int, total_weight_g: int}>  $orders
     * @return array{
     *     ranked_orders: list<array<string, mixed>>,
     *     waves: list<array<string, mixed>>,
     *     labor_estimate: array<string, mixed>
     * }
     */
    public function buildBoard(array $orders): array
    {
        $ranked = $this->scorer->scoreAndRank($orders);
        $waves = $this->batchOptimizer->buildWaves(array_map(static fn (array $o): array => [
            'order_id' => $o['order_id'],
            'line_count' => $o['line_count'],
            'total_weight_g' => $o['total_weight_g'],
            'cutoff_minutes' => $o['minutes_to_cutoff'],
        ], $orders));

        $totalLines = 0;
        foreach ($orders as $o) {
            $totalLines += (int) $o['line_count'];
        }

        $labor = $this->laborEstimator->estimate([
            'pick' => $totalLines,
            'pack' => $totalLines,
        ]);

        return [
            'ranked_orders' => $ranked,
            'waves' => $waves,
            'labor_estimate' => $labor,
        ];
    }
}
