<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Klasifikasi kecepatan/nilai SKU ke kelas ABC berbasis Pareto (80/15/5 = 8000/9500/10000 bps).
 */
final class AbcVelocityClassifier
{
    /**
     * @param  list<array{product_id: int, annual_value_idr: int}>  $items
     * @return list<array{product_id: int, annual_value_idr: int, cumulative_bps: int, abc_class: string}>
     */
    public function classify(array $items, int $classAThresholdBps = 8000, int $classBThresholdBps = 9500): array
    {
        if ($items === []) {
            return [];
        }
        if ($classAThresholdBps <= 0 || $classBThresholdBps <= $classAThresholdBps || $classBThresholdBps > 10000) {
            throw new InvalidArgumentException('Ambang batas bps ABC tidak valid.');
        }

        $sorted = [];
        $totalValue = 0;
        foreach ($items as $item) {
            $val = max(0, (int) $item['annual_value_idr']);
            $sorted[] = [
                'product_id' => (int) $item['product_id'],
                'annual_value_idr' => $val,
            ];
            $totalValue += $val;
        }

        usort($sorted, static fn (array $a, array $b): int => [$b['annual_value_idr'], $a['product_id']] <=> [$a['annual_value_idr'], $b['product_id']]);

        $running = 0;
        $out = [];
        foreach ($sorted as $row) {
            $running += $row['annual_value_idr'];
            $cumBps = $totalValue > 0 ? intdiv($running * 10000, $totalValue) : 10000;

            $class = match (true) {
                $cumBps <= $classAThresholdBps => 'A',
                $cumBps <= $classBThresholdBps => 'B',
                default => 'C',
            };

            $out[] = [
                'product_id' => $row['product_id'],
                'annual_value_idr' => $row['annual_value_idr'],
                'cumulative_bps' => $cumBps,
                'abc_class' => $class,
            ];
        }

        return $out;
    }
}
