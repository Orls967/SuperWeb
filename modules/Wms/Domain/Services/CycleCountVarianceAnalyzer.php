<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

/**
 * Analisis varians cycle count untuk mendeteksi SKU dengan shrinkage berulang.
 */
final class CycleCountVarianceAnalyzer
{
    /**
     * @param  list<array{product_id: int, system_qty: int, counted_qty: int, unit_cost_idr: int}>  $counts
     * @return array{
     *     total_system_value_idr: int,
     *     total_net_variance_idr: int,
     *     shrinkage_rate_bps: int,
     *     repeat_offender_products: list<int>
     * }
     */
    public function analyze(array $counts, int $shrinkageThresholdBps = 200): array
    {
        $totalSystemVal = 0;
        $netVarianceVal = 0;
        $shrinkageVal = 0;
        $negativeCountsByProduct = [];

        foreach ($counts as $row) {
            $sys = max(0, (int) $row['system_qty']);
            $cnt = max(0, (int) $row['counted_qty']);
            $cost = max(0, (int) $row['unit_cost_idr']);
            $pid = (int) $row['product_id'];

            $sysVal = $sys * $cost;
            $diff = $cnt - $sys;
            $diffVal = $diff * $cost;

            $totalSystemVal += $sysVal;
            $netVarianceVal += $diffVal;

            if ($diff < 0) {
                $shrinkageVal += abs($diffVal);
                $negativeCountsByProduct[$pid] = ($negativeCountsByProduct[$pid] ?? 0) + 1;
            }
        }

        $shrinkageRateBps = $totalSystemVal > 0 ? intdiv($shrinkageVal * 10000, $totalSystemVal) : 0;
        $repeatOffenders = [];
        foreach ($negativeCountsByProduct as $pid => $hits) {
            if ($hits >= 2 || $shrinkageRateBps > $shrinkageThresholdBps) {
                $repeatOffenders[] = $pid;
            }
        }
        sort($repeatOffenders);

        return [
            'total_system_value_idr' => $totalSystemVal,
            'total_net_variance_idr' => $netVarianceVal,
            'shrinkage_rate_bps' => $shrinkageRateBps,
            'repeat_offender_products' => $repeatOffenders,
        ];
    }
}
