<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use InvalidArgumentException;

/**
 * Validator distribusi partisi outbox berskala tinggi (D34/D35).
 * Memeriksa keseragaman alokasi hash key ke partisi $0 \dots K-1$.
 */
final class ScalePartitionValidatorService
{
    /**
     * @param  array<int, string>  $sampleKeys
     * @return array{
     *     partition_count: int,
     *     distribution: array<int, int>,
     *     max_deviation_pct: float,
     *     is_balanced: bool
     * }
     */
    public function analyzePartitionDistribution(array $sampleKeys, int $partitionCount): array
    {
        if ($partitionCount <= 0) {
            throw new InvalidArgumentException("Jumlah partisi harus positif: {$partitionCount}");
        }

        $distribution = array_fill(0, $partitionCount, 0);

        foreach ($sampleKeys as $k) {
            // Algoritma penentuan partisi: CRC32 % K
            $hash = crc32($k);
            $partitionIdx = (int) ($hash % $partitionCount);
            if ($partitionIdx < 0) {
                $partitionIdx += $partitionCount;
            }
            $distribution[$partitionIdx]++;
        }

        $total = count($sampleKeys);
        if ($total === 0) {
            return [
                'partition_count' => $partitionCount,
                'distribution' => $distribution,
                'max_deviation_pct' => 0.0,
                'is_balanced' => true,
            ];
        }

        $idealPerPartition = $total / $partitionCount;
        $maxDiff = 0.0;

        foreach ($distribution as $count) {
            $diff = abs($count - $idealPerPartition);
            if ($diff > $maxDiff) {
                $maxDiff = $diff;
            }
        }

        $deviationPct = round(($maxDiff / $idealPerPartition) * 100, 2);
        // Anggap seimbang jika deviasi maksimum <= 25% untuk sampel representatif
        $isBalanced = $deviationPct <= 25.0;

        return [
            'partition_count' => $partitionCount,
            'distribution' => $distribution,
            'max_deviation_pct' => $deviationPct,
            'is_balanced' => $isBalanced,
        ];
    }
}
