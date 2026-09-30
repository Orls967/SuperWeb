<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum TransportMode: string
{
    case ROAD = 'road';
    case SEA = 'sea';
    case AIR = 'air';

    public function label(): string
    {
        return match ($this) {
            self::ROAD => 'Darat (Trucking)',
            self::SEA => 'Laut (Vessel / Kontainer)',
            self::AIR => 'Udara (Air Freight)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ROAD => 'amber',
            self::SEA => 'cyan',
            self::AIR => 'purple',
        };
    }
}
