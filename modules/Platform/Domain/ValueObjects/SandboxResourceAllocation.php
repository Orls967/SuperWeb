<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object immutable alokasi kuota komputasi dan penyimpanan sandbox.
 */
final class SandboxResourceAllocation
{
    public function __construct(
        public readonly int $maxStorageMb,
        public readonly int $maxExecutionTimeSecs,
        public readonly int $memoryLimitMb,
        public readonly int $ttlHours,
        public readonly bool $allowExternalNetworkEgress
    ) {
        if ($this->maxStorageMb <= 0 || $this->memoryLimitMb <= 0) {
            throw new InvalidArgumentException('Batas resource storage dan memori harus lebih besar dari 0');
        }
    }

    public static function forTier(string $tier): self
    {
        return match ($tier) {
            'ephemeral' => new self(
                maxStorageMb: 256,
                maxExecutionTimeSecs: 300,
                memoryLimitMb: 512,
                ttlHours: 4,
                allowExternalNetworkEgress: false
            ),
            'isolated' => new self(
                maxStorageMb: 2048,
                maxExecutionTimeSecs: 1800,
                memoryLimitMb: 2048,
                ttlHours: 72,
                allowExternalNetworkEgress: true
            ),
            default => new self(
                maxStorageMb: 1024,
                maxExecutionTimeSecs: 600,
                memoryLimitMb: 1024,
                ttlHours: 24,
                allowExternalNetworkEgress: false
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'max_storage_mb' => $this->maxStorageMb,
            'max_execution_time_secs' => $this->maxExecutionTimeSecs,
            'memory_limit_mb' => $this->memoryLimitMb,
            'ttl_hours' => $this->ttlHours,
            'allow_egress' => $this->allowExternalNetworkEgress,
        ];
    }
}
