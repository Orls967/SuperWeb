<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Enums;

enum TableBookingStatus: string
{
    case REQUESTED = 'requested';
    case DEPOSIT_HELD = 'deposit_held';
    case CONFIRMED = 'confirmed';
    case SEATED = 'seated';
    case SETTLED = 'settled';
    case NO_SHOW_FORFEITED = 'no_show_forfeited';
    case CANCELLED_REFUNDED = 'cancelled_refunded';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => 'Permintaan Baru',
            self::DEPOSIT_HELD => 'Deposit Ditahan',
            self::CONFIRMED => 'Terkonfirmasi Sah',
            self::SEATED => 'Tamu Duduk di Meja',
            self::SETTLED => 'Tagihan Selesai Lunas',
            self::NO_SHOW_FORFEITED => 'Tamu Tidak Hadir (Denda 50%)',
            self::CANCELLED_REFUNDED => 'Dibatalkan & Refund Penuh',
        };
    }
}

