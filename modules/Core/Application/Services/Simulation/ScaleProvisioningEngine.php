<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\ValueObjects\Simulation\ScaleCapacityConfig;

/**
 * Mesin kalkulasi dan provisioning skala untuk infrastruktur outbox dan kernel simulasi.
 * Menghitung kebutuhan partisi DB, alokasi memori buffer, dan limit throughput per skala tier.
 */
final class ScaleProvisioningEngine
{
    /**
     * @param  string  $tier  'light'|'medium'|'heavy'
     * @return array{
     *     tier: string,
     *     config: array<string, mixed>,
     *     estimated_ram_mb: int,
     *     estimated_disk_mb_per_100_days: float,
     *     recommended_batch_size: int,
     *     max_concurrency: int
     * }
     */
    public function calculateRequirements(string $tier): array
    {
        $config = ScaleCapacityConfig::forTier($tier);

        // Model estimasi sumber daya:
        // RAM: basis 64MB + (partisi * 8MB) + (twin_batch_size * 0.1MB)
        $estimatedRam = (int) (64 + ($config->outboxPartitionCount * 8) + ($config->twinBatchSize * 0.1));

        // Disk per 100 hari virtual: (100 hari * 10 handlers * 0.5KB) + (100 * twin_count * 1KB)
        $diskPer100Days = round((100 * 10 * 0.5 / 1024) + (100 * $config->twinBatchSize * 1.0 / 1024), 2);

        return [
            'tier' => $config->tierName,
            'config' => $config->toArray(),
            'estimated_ram_mb' => $estimatedRam,
            'estimated_disk_mb_per_100_days' => $diskPer100Days,
            'recommended_batch_size' => $config->twinBatchSize,
            'max_concurrency' => $config->maxConcurrentStreams,
        ];
    }

    /**
     * Menghasilkan rencana migrasi partisi basis data virtual untuk skala tier tertentu.
     *
     * @return array<int, array{partition_id: int, partition_name: string, hash_range: string}>
     */
    public function generateOutboxPartitionPlan(string $tier): array
    {
        $config = ScaleCapacityConfig::forTier($tier);
        $count = $config->outboxPartitionCount;

        $partitions = [];
        $rangePerPartition = intdiv(65536, $count);

        for ($i = 0; $i < $count; $i++) {
            $start = $i * $rangePerPartition;
            $end = ($i === $count - 1) ? 65535 : ($start + $rangePerPartition - 1);

            $partitions[] = [
                'partition_id' => $i,
                'partition_name' => "core_outbox_p{$i}",
                'hash_range' => sprintf('0x%04x - 0x%04x', $start, $end),
            ];
        }

        return $partitions;
    }
}
