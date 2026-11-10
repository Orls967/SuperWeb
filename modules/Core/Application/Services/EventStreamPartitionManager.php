<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

/**
 * Pengelola Partisi Aliran Event Virtual (EventStreamPartitionManager)
 *
 * Mengalokasikan pesan event ke partisi virtual berdasarkan aggregate_key secara deterministik,
 * menjamin strict in-order processing untuk aggregate yang sama.
 */
class EventStreamPartitionManager
{
    public function __construct(
        protected int $totalPartitions = 16
    ) {}

    /**
     * Menghitung indeks partisi untuk kombinasi aggregate type dan id.
     */
    public function computePartition(string $aggregateType, string $aggregateId): int
    {
        $key = "{$aggregateType}:{$aggregateId}";
        $hash = abs(crc32($key));

        return $hash % $this->totalPartitions;
    }

    /**
     * Mengelompokkan sekumpulan event ke dalam partisi-partisi virtual.
     *
     * @param  array<int, array{aggregate_type: string, aggregate_id: string, payload: mixed}>  $events
     * @return array<int, array<int, array{aggregate_type: string, aggregate_id: string, payload: mixed}>>
     */
    public function partitionEvents(array $events): array
    {
        $partitions = [];
        for ($i = 0; $i < $this->totalPartitions; $i++) {
            $partitions[$i] = [];
        }

        foreach ($events as $event) {
            $p = $this->computePartition($event['aggregate_type'], $event['aggregate_id']);
            $partitions[$p][] = $event;
        }

        return $partitions;
    }

    public function getTotalPartitions(): int
    {
        return $this->totalPartitions;
    }
}
