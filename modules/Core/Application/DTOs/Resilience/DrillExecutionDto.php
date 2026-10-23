<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTOs\Resilience;

final readonly class DrillExecutionDto
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $drillType,
        public string $primaryRegion,
        public string $targetDrRegion,
        public int $simulatedLagSeconds,
        public ?int $userId = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            drillType: (string) ($data['drill_type'] ?? 'FAILOVER_SIMULATION'),
            primaryRegion: (string) ($data['primary_region'] ?? 'ap-southeast-3'),
            targetDrRegion: (string) ($data['target_dr_region'] ?? 'ap-southeast-1'),
            simulatedLagSeconds: (int) ($data['simulated_lag_seconds'] ?? 15),
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }
}
