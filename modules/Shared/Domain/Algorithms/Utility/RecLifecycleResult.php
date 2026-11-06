<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

/**
 * Data hasil evaluasi transisi siklus hidup REC.
 */
final class RecLifecycleResult
{
    public function __construct(
        public readonly string $serialNumber,
        public readonly string $previousStatus,
        public readonly string $newStatus,
        public readonly int $energyWh,
        public readonly int $generatorAssetId,
        public readonly string $vintageYear,
        public readonly string $ownerPartyId,
        public readonly string $claimReason,
        public readonly bool $isRetired,
        public readonly string $certificateHash,
        public readonly string $canonicalPayload,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'serial_number' => $this->serialNumber,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'energy_wh' => $this->energyWh,
            'generator_asset_id' => $this->generatorAssetId,
            'vintage_year' => $this->vintageYear,
            'owner_party_id' => $this->ownerPartyId,
            'claim_reason' => $this->claimReason,
            'is_retired' => $this->isRetired,
            'certificate_hash' => $this->certificateHash,
            'canonical_payload' => $this->canonicalPayload,
        ];
    }
}
