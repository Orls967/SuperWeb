<?php

declare(strict_types=1);

namespace Modules\Store\Domain\Enums;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'Menunggu Pembayaran',
            self::PAID => 'Sudah Dibayar',
            self::PROCESSING => 'Diproses',
            self::SHIPPED => 'Dikirim',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
            self::REFUNDED => 'Dikembalikan (Refund)',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            self::PAID => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
            self::PROCESSING => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
            self::SHIPPED => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
            self::COMPLETED => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            self::CANCELLED => 'bg-red-500/10 text-red-400 border-red-500/20',
            self::REFUNDED => 'bg-zinc-500/10 text-zinc-400 border-zinc-500/20',
        };
    }
}
