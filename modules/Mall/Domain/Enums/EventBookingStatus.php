<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum EventBookingStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf Pengajuan',
            self::CONFIRMED => 'Terkonfirmasi',
            self::ONGOING => 'Sedang Berlangsung',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
