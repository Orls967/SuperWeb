<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum AssetCategory: string
{
    case HVAC = 'hvac';
    case ELEVATOR = 'elevator';
    case ELECTRICAL = 'electrical';
    case PLUMBING = 'plumbing';
    case FIRE_SAFETY = 'fire_safety';
    case CIVIL = 'civil';

    public function label(): string
    {
        return match ($this) {
            self::HVAC => 'Tata Udara & AC (HVAC)',
            self::ELEVATOR => 'Lift & Eskalator',
            self::ELECTRICAL => 'Kelistrikan & Genset',
            self::PLUMBING => 'Plumbing & Pompa Air',
            self::FIRE_SAFETY => 'Proteksi Kebakaran (Hydrant/APAR)',
            self::CIVIL => 'Sipil & Bangunan Fisik',
        };
    }
}
