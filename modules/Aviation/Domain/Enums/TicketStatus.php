<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Enums;

enum TicketStatus: string
{
    case HELD = 'held';
    case PAID = 'paid';
    case ISSUED = 'issued';
    case CHECKED_IN = 'checked_in';
    case BOARDED = 'boarded';
    case FLOWN = 'flown';
    case REFUNDED = 'refunded';
    case NO_SHOW = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::HELD => 'Hold Sementara',
            self::PAID => 'Telah Dibayar',
            self::ISSUED => 'Tiket Terbit (PNR Aktif)',
            self::CHECKED_IN => 'Sudah Check-In',
            self::BOARDED => 'Masuk Pesawat',
            self::FLOWN => 'Penerbangan Selesai',
            self::REFUNDED => 'Dana Dikembalikan',
            self::NO_SHOW => 'Tidak Hadir (No-Show)',
        };
    }
}
