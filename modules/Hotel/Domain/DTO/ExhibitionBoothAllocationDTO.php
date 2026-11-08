<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\DTO;

final class ExhibitionBoothAllocationDTO
{
    /**
     * @param  array<string, float>  $utilityLoads
     * @param  array<string, mixed>  $spatialDimensions
     */
    public function __construct(
        public readonly string $reservationId,
        public readonly string $boothCode,
        public readonly string $exhibitorName,
        public readonly string $hallZone,
        public readonly float $boothAreaSqm,
        public readonly float $baseRentalRate,
        public readonly float $utilitySurcharge,
        public readonly int $totalCharge,
        public readonly array $utilityLoads,
        public readonly array $spatialDimensions,
        public readonly string $status = 'reserved'
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reservation_id' => $this->reservationId,
            'booth_code' => $this->boothCode,
            'exhibitor_name' => $this->exhibitorName,
            'hall_zone' => $this->hallZone,
            'booth_area_sqm' => $this->boothAreaSqm,
            'base_rental_rate' => $this->baseRentalRate,
            'utility_surcharge' => $this->utilitySurcharge,
            'total_charge' => $this->totalCharge,
            'utility_loads' => $this->utilityLoads,
            'spatial_dimensions' => $this->spatialDimensions,
            'status' => $this->status,
        ];
    }
}
