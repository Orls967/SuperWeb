<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum TrailerType: string
{
    case FLATBED_20 = 'flatbed_20';
    case FLATBED_40 = 'flatbed_40';
    case SKELETAL_20 = 'skeletal_20';
    case SKELETAL_40 = 'skeletal_40';
    case REEFER = 'reefer';

    public function label(): string
    {
        return match ($this) {
            self::FLATBED_20 => "Flatbed 20' (Muatan Umum / Breakbulk)",
            self::FLATBED_40 => "Flatbed 40' (Muatan Panjang / Heavy)",
            self::SKELETAL_20 => "Skeletal Chassis 20' (Kontainer 20ft)",
            self::SKELETAL_40 => "Skeletal Chassis 40' (Kontainer 40ft/45ft)",
            self::REEFER => "Trailer Reefer 40' Genset (Cold Chain)",
        };
    }
}
