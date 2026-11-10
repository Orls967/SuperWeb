<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects\Simulation;

use InvalidArgumentException;

/**
 * Value Object parameter kapasitas scale tier untuk beban simulasi & outbox.
 */
final readonly class ScaleCapacityConfig
{
    public function __construct(
        public string $tierName,
        public int $maxVirtualDays,
        public int $outboxPartitionCount,
        public int $twinBatchSize,
        public int $checkpointIntervalDays,
        public int $maxConcurrentStreams,
        public bool $memoryCacheEnabled,
    ) {
        if ($this->maxVirtualDays <= 0) {
            throw new InvalidArgumentException('Maksimum hari virtual harus > 0.');
        }
        if ($this->outboxPartitionCount <= 0) {
            throw new InvalidArgumentException('Jumlah partisi outbox harus >= 1.');
        }
    }

    public static function forTier(string $tier): self
    {
        return match (strtolower(trim($tier))) {
            'light' => new self(
                tierName: 'light',
                maxVirtualDays: 30,
                outboxPartitionCount: 2,
                twinBatchSize: 50,
                checkpointIntervalDays: 1,
                maxConcurrentStreams: 5,
                memoryCacheEnabled: false,
            ),
            'medium' => new self(
                tierName: 'medium',
                maxVirtualDays: 365,
                outboxPartitionCount: 8,
                twinBatchSize: 200,
                checkpointIntervalDays: 7,
                maxConcurrentStreams: 20,
                memoryCacheEnabled: true,
            ),
            'heavy', 'enterprise' => new self(
                tierName: 'heavy',
                maxVirtualDays: 1825,
                outboxPartitionCount: 32,
                twinBatchSize: 1000,
                checkpointIntervalDays: 30,
                maxConcurrentStreams: 100,
                memoryCacheEnabled: true,
            ),
            default => throw new InvalidArgumentException("Scale tier tidak dikenal: {$tier}"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tier_name' => $this->tierName,
            'max_virtual_days' => $this->maxVirtualDays,
            'outbox_partition_count' => $this->outboxPartitionCount,
            'twin_batch_size' => $this->twinBatchSize,
            'checkpoint_interval_days' => $this->checkpointIntervalDays,
            'max_concurrent_streams' => $this->maxConcurrentStreams,
            'memory_cache_enabled' => $this->memoryCacheEnabled,
        ];
    }
}
