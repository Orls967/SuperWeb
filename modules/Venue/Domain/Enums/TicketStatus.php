<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Enums;

enum TicketStatus: string
{
    case RESERVED = 'reserved';
    case ISSUED = 'issued';
    case TRANSFERRED = 'transferred';
    case SCANNED_IN = 'scanned_in';
    case SCANNED_OUT = 'scanned_out';
    case USED = 'used';
    case REFUNDED = 'refunded';
    case VOID = 'void';

    public function label(): string
    {
        return match ($this) {
            self::RESERVED => 'Dipesan Sementara',
            self::ISSUED => 'Terbit Sah',
            self::TRANSFERRED => 'Ditransfer ke Pihak Lain',
            self::SCANNED_IN => 'Berada di Dalam Venue',
            self::SCANNED_OUT => 'Berada di Luar Venue (Re-entry)',
            self::USED => 'Selesai Digunakan',
            self::REFUNDED => 'Dikembalikan Penuh',
            self::VOID => 'Hangus / Dibatalkan',
        };
    }
}
