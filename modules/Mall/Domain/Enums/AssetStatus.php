<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum AssetStatus: string
{
    case OPERATIONAL = 'operational';
    case MAINTENANCE = 'maintenance';
    case BROKEN = 'broken';
    case DECOMMISSIONED = 'decommissioned';

    public function label(): string
    {
        return match ($this) {
            self::OPERATIONAL => 'Beroperasi Normal',
            self::MAINTENANCE => 'Dalam Perawatan / Servis',
            self::BROKEN => 'Rusak / Gangguan',
            self::DECOMMISSIONED => 'Dinonaktifkan / Pensiun',
        };
    }
}
