<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Pengelompokan order outbound ke dalam pick wave berdasarkan batas kapasitas & carrier cut-off.
 */
final class WaveBatchOptimizer
{
    /**
     * @param  list<array{order_id: string, line_count: int, total_weight_g: int, cutoff_minutes: int}>  $orders
     * @return list<array{wave_index: int, order_ids: list<string>, total_lines: int, total_weight_g: int}>
     */
    public function buildWaves(array $orders, int $maxLinesPerWave = 50, int $maxWeightGramsPerWave = 500000): array
    {
        if ($maxLinesPerWave <= 0 || $maxWeightGramsPerWave <= 0) {
            throw new InvalidArgumentException('Kapasitas wave harus > 0.');
        }
        if ($orders === []) {
            return [];
        }

        usort($orders, static fn (array $a, array $b): int => [$a['cutoff_minutes'], $a['order_id']] <=> [$b['cutoff_minutes'], $b['order_id']]);

        $waves = [];
        $currentOrders = [];
        $currentLines = 0;
        $currentWeight = 0;
        $waveIndex = 1;

        foreach ($orders as $order) {
            $lines = (int) $order['line_count'];
            $weight = (int) $order['total_weight_g'];

            if ($currentOrders !== [] && ($currentLines + $lines > $maxLinesPerWave || $currentWeight + $weight > $maxWeightGramsPerWave)) {
                $waves[] = [
                    'wave_index' => $waveIndex++,
                    'order_ids' => $currentOrders,
                    'total_lines' => $currentLines,
                    'total_weight_g' => $currentWeight,
                ];
                $currentOrders = [];
                $currentLines = 0;
                $currentWeight = 0;
            }

            $currentOrders[] = (string) $order['order_id'];
            $currentLines += $lines;
            $currentWeight += $weight;
        }

        if ($currentOrders !== []) {
            $waves[] = [
                'wave_index' => $waveIndex,
                'order_ids' => $currentOrders,
                'total_lines' => $currentLines,
                'total_weight_g' => $currentWeight,
            ];
        }

        return $waves;
    }
}
