<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\ValueObjects;

use InvalidArgumentException;

final class GpsCoordinates
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude
    ) {
        if ($this->latitude < -90.0 || $this->latitude > 90.0) {
            throw new InvalidArgumentException("Latitude must be between -90 and 90, got: {$this->latitude}");
        }

        if ($this->longitude < -180.0 || $this->longitude > 180.0) {
            throw new InvalidArgumentException("Longitude must be between -180 and 180, got: {$this->longitude}");
        }
    }

    public function distanceTo(self $destination): float
    {
        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($destination->latitude);
        $lonTo = deg2rad($destination->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            sin($latDelta / 2) ** 2 +
            cos($latFrom) * cos($latTo) * (sin($lonDelta / 2) ** 2)
        ));

        return round($angle * self::EARTH_RADIUS_KM, 3);
    }

    public function isWithinRadius(self $destination, float $maxRadiusKm): bool
    {
        return $this->distanceTo($destination) <= $maxRadiusKm;
    }

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
