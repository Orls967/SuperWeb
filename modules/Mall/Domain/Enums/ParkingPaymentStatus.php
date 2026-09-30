<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum ParkingPaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PAID = 'paid';
    case WAIVED = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum Dibayar',
            self::PAID => 'Lunas',
            self::WAIVED => 'Bebas Bayar (Gratis)',
        };
    }
}
