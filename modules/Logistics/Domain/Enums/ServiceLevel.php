<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum ServiceLevel: string
{
    case SameDay = 'same_day';
    case Express = 'express';
    case Regular = 'regular';
    case Economy = 'economy';
    case LTL = 'ltl';
    case FTL = 'ftl';
    case LCL = 'lcl';
    case FCL = 'fcl';
    case AirFreight = 'air_freight';

    public function label(): string
    {
        return match ($this) {
            self::SameDay => 'Same Day (Hari Sama)',
            self::Express => 'Express (1 Hari)',
            self::Regular => 'Reguler (2-3 Hari)',
            self::Economy => 'Ekonomi (Kargo Darat/Laut)',
            self::LTL => 'LTL (Less Than Truckload)',
            self::FTL => 'FTL (Full Truckload)',
            self::LCL => 'LCL (Less Container Load)',
            self::FCL => 'FCL (Full Container Load)',
            self::AirFreight => 'Air Freight (Kargo Udara)',
        };
    }

    public function defaultMode(): TransportMode
    {
        return match ($this) {
            self::SameDay, self::Express, self::Regular, self::LTL, self::FTL => TransportMode::ROAD,
            self::Economy, self::LCL, self::FCL => TransportMode::SEA,
            self::AirFreight => TransportMode::AIR,
        };
    }

    public function isUnitBased(): bool
    {
        return in_array($this, [self::FTL, self::FCL], true);
    }
}
