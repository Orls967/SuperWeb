<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum VehicleType: string
{
    case CAR = 'car';
    case MOTORCYCLE = 'motorcycle';
    case TRUCK = 'truck';

    public function label(): string
    {
        return match ($this) {
            self::CAR => 'Mobil Penumpang',
            self::MOTORCYCLE => 'Sepeda Motor',
            self::TRUCK => 'Truk / Kendaraan Box',
        };
    }
}
