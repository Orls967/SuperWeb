<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum ParkingSessionStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case LOST = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Sedang Parkir',
            self::COMPLETED => 'Selesai (Sudah Keluar)',
            self::LOST => 'Tiket Hilang',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'bg-blue-100 text-blue-800 border-blue-200',
            self::COMPLETED => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::LOST => 'bg-rose-100 text-rose-800 border-rose-200',
        };
    }
}
