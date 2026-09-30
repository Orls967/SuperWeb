<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum OrderStatus: string
{
    case OPEN = 'open';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PAID = 'paid';
    case VOID = 'void';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Terbuka',
            self::AWAITING_PAYMENT => 'Menunggu Pembayaran',
            self::PAID => 'Lunas',
            self::VOID => 'Dibatalkan (Void)',
            self::REFUNDED => 'Dikembalikan (Refund)',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::OPEN => in_array($target, [self::AWAITING_PAYMENT, self::PAID, self::VOID], true),
            self::AWAITING_PAYMENT => in_array($target, [self::PAID, self::VOID, self::OPEN], true),
            self::PAID => in_array($target, [self::REFUNDED, self::VOID], true),
            self::VOID => false,
            self::REFUNDED => false,
        };
    }
}
