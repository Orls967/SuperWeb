<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Enums;

enum CargoConsignmentStatus: string
{
    case BOOKED = 'booked';
    case ACCEPTED = 'accepted';
    case DG_CLEARED = 'dg_cleared';
    case LOADED = 'loaded';
    case IN_FLIGHT = 'in_flight';
    case DELIVERED = 'delivered';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::BOOKED => 'Dibooking',
            self::ACCEPTED => 'Diterima di Gudang',
            self::DG_CLEARED => 'Lolos Uji DG & Suhu',
            self::LOADED => 'Masuk Kontainer ULD',
            self::IN_FLIGHT => 'Dalam Penerbangan',
            self::DELIVERED => 'Terkirim di Tujuan',
            self::REJECTED => 'Ditolak (Non-Compliant)',
        };
    }
}
