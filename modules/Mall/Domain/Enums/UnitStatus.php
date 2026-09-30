<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum UnitStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case LEASED = 'leased';
    case MAINTENANCE = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Tersedia',
            self::RESERVED => 'Dipesan (Reserved)',
            self::LEASED => 'Tersewa (Aktif)',
            self::MAINTENANCE => 'Renovasi / Maintenance',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::AVAILABLE => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::RESERVED => 'bg-amber-100 text-amber-800 border-amber-200',
            self::LEASED => 'bg-blue-100 text-blue-800 border-blue-200',
            self::MAINTENANCE => 'bg-rose-100 text-rose-800 border-rose-200',
        };
    }
}
