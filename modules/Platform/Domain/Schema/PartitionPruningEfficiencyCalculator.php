<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class PartitionPruningEfficiencyCalculator
{
    /**
     * Calculates efficiency percentage gained by partition pruning for a given date range filter.
     *
     * @param  int  $totalPartitions  e.g. 36 (3 years monthly)
     * @param  int  $scannedPartitions  e.g. 2 (60 days range)
     * @return array{pruning_efficiency_pct: float, partitions_pruned: int, is_optimal: bool}
     */
    public function calculateEfficiency(int $totalPartitions, int $scannedPartitions): array
    {
        $total = max(1, $totalPartitions);
        $scanned = max(1, min($total, $scannedPartitions));

        $pruned = $total - $scanned;
        $efficiencyPct = round(($pruned / $total) * 100.0, 2);

        return [
            'pruning_efficiency_pct' => $efficiencyPct,
            'partitions_pruned' => $pruned,
            'is_optimal' => ($efficiencyPct >= 75.0),
        ];
    }
}
