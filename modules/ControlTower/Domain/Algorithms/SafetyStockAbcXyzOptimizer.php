<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Algorithms;

use InvalidArgumentException;
use Modules\ControlTower\Domain\Certificates\SafetyStockPolicyCertificate;

final class SafetyStockAbcXyzOptimizer
{
    /**
     * @param  list<int>  $demandHistory
     * @return array{
     *     item_code: string,
     *     abc_class: string,
     *     xyz_class: string,
     *     cv_bps: int,
     *     safety_stock_qty: int,
     *     reorder_point_qty: int,
     *     certificate: SafetyStockPolicyCertificate
     * }
     */
    public function optimize(
        string $itemCode,
        int $annualUsageValueIdr,
        int $totalCatalogValueIdr,
        array $demandHistory,
        int $avgLeadTimeDays,
        int $leadTimeStdDevBps,
        int $serviceLevelBps = 9500,
    ): array {
        if ($itemCode === '' || $demandHistory === [] || $avgLeadTimeDays < 1) {
            throw new InvalidArgumentException('INVALID_SAFETY_STOCK_INPUT: Parameter SKU atau historis permintaan tidak valid.');
        }

        $valueShareBps = $totalCatalogValueIdr > 0
            ? intdiv($annualUsageValueIdr * 10_000, $totalCatalogValueIdr)
            : 0;

        $abcClass = match (true) {
            $valueShareBps >= 2000 => 'A',
            $valueShareBps >= 500 => 'B',
            default => 'C',
        };

        $n = count($demandHistory);
        $mean = intdiv(array_sum($demandHistory), $n);
        $varianceSum = 0;
        foreach ($demandHistory as $d) {
            $diff = $d - $mean;
            $varianceSum += $diff * $diff;
        }
        $stdDev = $this->isqrt(intdiv($varianceSum, $n));
        $cvBps = $mean > 0 ? intdiv($stdDev * 10_000, $mean) : 10_000;

        $xyzClass = match (true) {
            $cvBps <= 1500 => 'X',
            $cvBps <= 4000 => 'Y',
            default => 'Z',
        };

        // Z-factor in basis points: 90% -> 12800 (1.28), 95% -> 16500 (1.65), 99% -> 23300 (2.33)
        $zFactorBps = match (true) {
            $serviceLevelBps >= 9900 => 23_300,
            $serviceLevelBps >= 9500 => 16_500,
            default => 12_800,
        };

        // Integer safety stock = ceil((zFactor / 10000) * (stdDev * sqrt(leadTime) + mean * (leadTimeStdDevBps/10000)))
        $sqrtLeadTimeBps = $this->isqrt($avgLeadTimeDays * 100_000_000); // scaled by 10_000
        $demandComponent = intdiv($stdDev * $sqrtLeadTimeBps, 10_000);
        $leadTimeComponent = intdiv($mean * $leadTimeStdDevBps, 10_000);
        $safetyStockQty = max(1, intdiv($zFactorBps * ($demandComponent + $leadTimeComponent) + 9999, 10_000));
        $reorderPointQty = max($safetyStockQty, $mean * $avgLeadTimeDays + $safetyStockQty);

        $digest = hash('sha256', implode('|', [
            $itemCode,
            $abcClass,
            $xyzClass,
            (string) $serviceLevelBps,
            (string) $safetyStockQty,
            (string) $reorderPointQty,
        ]));

        return [
            'item_code' => $itemCode,
            'abc_class' => $abcClass,
            'xyz_class' => $xyzClass,
            'cv_bps' => $cvBps,
            'safety_stock_qty' => $safetyStockQty,
            'reorder_point_qty' => $reorderPointQty,
            'certificate' => new SafetyStockPolicyCertificate(
                itemCode: $itemCode,
                abcClass: $abcClass,
                xyzClass: $xyzClass,
                serviceLevelBps: $serviceLevelBps,
                safetyStockQty: $safetyStockQty,
                reorderPointQty: $reorderPointQty,
                digest: $digest,
            ),
        ];
    }

    private function isqrt(int $n): int
    {
        if ($n <= 0) {
            return 0;
        }

        $x = $n;
        $y = intdiv($x + 1, 2);
        while ($y < $x) {
            $x = $y;
            $y = intdiv($x + intdiv($n, $x), 2);
        }

        return $x;
    }
}
