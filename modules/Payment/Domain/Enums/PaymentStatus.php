<?php

declare(strict_types=1);

namespace Modules\Payment\Domain\Enums;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PAID = 'paid';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum Lunas',
            self::PAID => 'Lunas',
            self::REFUNDED => 'Dikembalikan (Refund)',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::UNPAID => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
            self::PAID => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
            self::REFUNDED => 'bg-purple-500/20 text-purple-300 border-purple-500/30',
        };
    }
}
