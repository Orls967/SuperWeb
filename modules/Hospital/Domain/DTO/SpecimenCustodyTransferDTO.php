<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\DTO;

readonly class SpecimenCustodyTransferDTO
{
    public function __construct(
        public int $specimenId,
        public int $hopNumber,
        public string $fromHandler,
        public string $toHandler,
        public ?float $temperatureCelsius = null,
        public ?string $notes = null,
        public ?string $gpsCoordinates = null,
        public ?string $containerBarcode = null
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            specimenId: (int) ($data['specimen_id'] ?? 0),
            hopNumber: (int) ($data['hop_number'] ?? 1),
            fromHandler: (string) ($data['from_handler'] ?? ''),
            toHandler: (string) ($data['to_handler'] ?? ''),
            temperatureCelsius: isset($data['transport_temp_celsius']) ? (float) $data['transport_temp_celsius'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            gpsCoordinates: isset($data['gps_coordinates']) ? (string) $data['gps_coordinates'] : null,
            containerBarcode: isset($data['storage_container_barcode']) ? (string) $data['storage_container_barcode'] : null,
        );
    }
}
