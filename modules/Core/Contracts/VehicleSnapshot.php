<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Immutable snapshot DTO of a vehicle for cross-module boundary access (W8-D44).
 */
final class VehicleSnapshot
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $uuid,
        public readonly ?int $userId,
        public readonly ?int $carId,
        public readonly ?string $plateNumber,
        public readonly ?string $vin,
        public readonly ?string $color,
        public readonly int $odometerKm,
        public readonly string $status,
        public readonly ?string $acquiredAt = null,
        public readonly ?string $passportUrl = null,
    ) {}

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user_id' => $this->userId,
            'car_id' => $this->carId,
            'plate_number' => $this->plateNumber,
            'vin' => $this->vin,
            'color' => $this->color,
            'odometer_km' => $this->odometerKm,
            'status' => $this->status,
            'acquired_at' => $this->acquiredAt,
            'passport_url' => $this->passportUrl,
        ];
    }
}
