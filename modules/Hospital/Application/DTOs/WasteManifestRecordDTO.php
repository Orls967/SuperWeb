<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class WasteManifestRecordDTO
{
    public function __construct(
        public readonly string $manifestNumber,
        public readonly string $wasteCategory,
        public readonly float $weightKg,
        public readonly string $sourceWardCode,
        public readonly string $handlerPartyName,
        public readonly ?string $transporterPlateNo,
        public readonly string $custodyHash,
        public readonly string $status,
        public readonly string $timestampIso,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'manifest_number' => $this->manifestNumber,
            'waste_category' => $this->wasteCategory,
            'weight_kg' => $this->weightKg,
            'source_ward_code' => $this->sourceWardCode,
            'handler_party_name' => $this->handlerPartyName,
            'transporter_plate_no' => $this->transporterPlateNo,
            'custody_hash' => $this->custodyHash,
            'status' => $this->status,
            'timestamp' => $this->timestampIso,
        ];
    }
}
