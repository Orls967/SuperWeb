<?php

declare(strict_types=1);

namespace Modules\Core\Application\Partitioning;

use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Pemantau Kesehatan Partisi Stream (PartitionHealthMonitor)
 *
 * Mengukur distribusi muatan (load skew), throughput, dan rasio lag
 * pada partisi-partisi virtual Universal Event Spine untuk mendeteksi partition hotspot.
 */
class PartitionHealthMonitor
{
    /**
     * Menganalisis metrik distribusi beban pada partisi virtual.
     *
     * @return array{
     *     total_partitions: int,
     *     total_events: int,
     *     average_load: float,
     *     standard_deviation: float,
     *     coefficient_of_variation: float,
     *     is_skewed: bool,
     *     hottest_partition: array{partition_index: int, event_count: int, load_percentage: float},
     *     coldest_partition: array{partition_index: int, event_count: int, load_percentage: float},
     *     partition_distribution: array<int, array{
     *         partition_index: int,
     *         event_count: int,
     *         percentage: float,
     *         status: 'NORMAL'|'HOT'|'COLD'
     *     }>,
     *     rebalance_recommended: bool
     * }
     */
    public function inspect(int $totalPartitions = 16): array
    {
        $events = SimEventSpine::query()->latest('id')->take(10000)->get(['id', 'aggregate_type', 'aggregate_id', 'topic']);
        $totalEvents = $events->count();

        $partitionCounts = array_fill(0, $totalPartitions, 0);

        foreach ($events as $event) {
            $type = (string) ($event->aggregate_type ?? 'generic');
            $id = (string) ($event->aggregate_id ?? $event->id);
            $idx = $this->calculatePartitionIndex($type, $id, $totalPartitions);
            $partitionCounts[$idx]++;
        }

        $avgLoad = $totalEvents > 0 ? ($totalEvents / $totalPartitions) : 0.0;

        // Hitung standar deviasi
        $varianceSum = 0.0;
        foreach ($partitionCounts as $count) {
            $varianceSum += pow($count - $avgLoad, 2);
        }
        $stdDev = $totalPartitions > 0 ? sqrt($varianceSum / $totalPartitions) : 0.0;
        $cv = $avgLoad > 0 ? ($stdDev / $avgLoad) : 0.0;

        $hottestIdx = 0;
        $coldestIdx = 0;
        $maxVal = -1;
        $minVal = PHP_INT_MAX;

        $distribution = [];
        foreach ($partitionCounts as $idx => $count) {
            if ($count > $maxVal) {
                $maxVal = $count;
                $hottestIdx = $idx;
            }
            if ($count < $minVal) {
                $minVal = $count;
                $coldestIdx = $idx;
            }

            $percentage = $totalEvents > 0 ? round(($count / $totalEvents) * 100, 2) : 0.0;
            $status = 'NORMAL';
            if ($avgLoad > 0) {
                if ($count > $avgLoad * 1.5) {
                    $status = 'HOT';
                } elseif ($count < $avgLoad * 0.5) {
                    $status = 'COLD';
                }
            }

            $distribution[] = [
                'partition_index' => $idx,
                'event_count' => $count,
                'percentage' => $percentage,
                'status' => $status,
            ];
        }

        // Partisi dinilai skewed jika koefisien variasi > 0.40 (deviasi tinggi)
        $isSkewed = $cv > 0.40 && $totalEvents > 50;
        $rebalanceRecommended = $isSkewed;

        return [
            'total_partitions' => $totalPartitions,
            'total_events' => $totalEvents,
            'average_load' => round($avgLoad, 2),
            'standard_deviation' => round($stdDev, 2),
            'coefficient_of_variation' => round($cv, 4),
            'is_skewed' => $isSkewed,
            'hottest_partition' => [
                'partition_index' => $hottestIdx,
                'event_count' => max(0, $maxVal),
                'load_percentage' => $totalEvents > 0 ? round(($maxVal / $totalEvents) * 100, 2) : 0.0,
            ],
            'coldest_partition' => [
                'partition_index' => $coldestIdx,
                'event_count' => $minVal === PHP_INT_MAX ? 0 : $minVal,
                'load_percentage' => ($totalEvents > 0 && $minVal !== PHP_INT_MAX) ? round(($minVal / $totalEvents) * 100, 2) : 0.0,
            ],
            'partition_distribution' => $distribution,
            'rebalance_recommended' => $rebalanceRecommended,
        ];
    }

    /**
     * Menghitung index partisi kanonikal menggunakan FNV-1a hash.
     */
    protected function calculatePartitionIndex(string $aggregateType, string $aggregateId, int $totalPartitions): int
    {
        $key = "{$aggregateType}:{$aggregateId}";
        $hash = 0x811C9DC5;

        $len = strlen($key);
        for ($i = 0; $i < $len; $i++) {
            $hash ^= ord($key[$i]);
            $hash = ($hash * 0x01000193) & 0xFFFFFFFF;
        }

        return abs($hash % max(1, $totalPartitions));
    }
}
