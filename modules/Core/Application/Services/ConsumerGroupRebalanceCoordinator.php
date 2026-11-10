<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

/**
 * Koordinator Rebalance Grup Konsumen (ConsumerGroupRebalanceCoordinator)
 *
 * Mengalokasikan partisi stream secara adil (round-robin / range assignment)
 * ke sejumlah consumer worker aktif dalam suatu consumer group.
 */
class ConsumerGroupRebalanceCoordinator
{
    /**
     * Alokasikan rentang partisi ke sejumlah worker yang aktif.
     *
     * @param  array<int, string>  $workerIds  Daftar ID worker aktif
     * @param  int  $totalPartitions  Jumlah total partisi
     * @return array<string, array<int, int>> Pemetaan worker_id => list partisi
     */
    public function assignPartitions(array $workerIds, int $totalPartitions = 16): array
    {
        if (empty($workerIds)) {
            return [];
        }

        // Urutkan worker agar deterministik
        $sortedWorkers = array_values($workerIds);
        sort($sortedWorkers);

        $numWorkers = count($sortedWorkers);
        $assignment = [];

        foreach ($sortedWorkers as $w) {
            $assignment[$w] = [];
        }

        for ($p = 0; $p < $totalPartitions; $p++) {
            $workerIndex = $p % $numWorkers;
            $workerId = $sortedWorkers[$workerIndex];
            $assignment[$workerId][] = $p;
        }

        return $assignment;
    }
}
