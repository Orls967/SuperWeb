<?php

declare(strict_types=1);

namespace Modules\Payment\Domain\Enums;

enum PaymentIntentStatus: string
{
    case PENDING = 'pending';
    case HELD = 'held';
    case CAPTURED = 'captured';
    case RELEASED = 'released';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case EXPIRED = 'expired';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PENDING => in_array($next, [self::HELD, self::CAPTURED, self::FAILED], true),
            self::HELD => in_array($next, [self::CAPTURED, self::RELEASED, self::EXPIRED, self::FAILED], true),
            self::CAPTURED => in_array($next, [self::REFUNDED], true),
            self::RELEASED, self::FAILED, self::REFUNDED, self::EXPIRED => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Pembayaran',
            self::HELD => 'Dana Ditahan (Escrow Hold)',
            self::CAPTURED => 'Berhasil Dibayar (Captured)',
            self::RELEASED => 'Dana Dilepaskan (Released)',
            self::FAILED => 'Gagal',
            self::REFUNDED => 'Dikembalikan (Refunded)',
            self::EXPIRED => 'Kadaluarsa',
        };
    }
}
