<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTO;

final readonly class TwinAuditReportDTO
{
    /**
     * @param  array<int, array<string, mixed>>  $discrepancies
     */
    public function __construct(
        public string $entityType,
        public string $entityId,
        public bool $isChainValid,
        public int $totalStates,
        public int $totalSnapshots,
        public ?string $latestHash,
        public array $discrepancies = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'is_chain_valid' => $this->isChainValid,
            'total_states' => $this->totalStates,
            'total_snapshots' => $this->totalSnapshots,
            'latest_hash' => $this->latestHash,
            'discrepancies' => $this->discrepancies,
        ];
    }
}
