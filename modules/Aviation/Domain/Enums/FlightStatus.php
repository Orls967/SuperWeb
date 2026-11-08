<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Enums;

enum FlightStatus: string
{
    case PLANNED = 'planned';
    case SLOT_SECURED = 'slot_secured';
    case AIRWORTHY_CLEARED = 'airworthy_cleared';
    case BOARDING = 'boarding';
    case DEPARTED = 'departed';
    case ARRIVED = 'arrived';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Direncanakan',
            self::SLOT_SECURED => 'Slot Terkonfirmasi',
            self::AIRWORTHY_CLEARED => 'Lolos Kelaikan & W&B',
            self::BOARDING => 'Sedang Boarding',
            self::DEPARTED => 'Berangkat (Off-Block)',
            self::ARRIVED => 'Mendarat (On-Block)',
            self::CLOSED => 'Selesai Ditutup',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}

