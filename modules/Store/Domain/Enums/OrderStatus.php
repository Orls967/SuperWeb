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

    // C2C (jual beli antar pengguna) dengan dana ditahan di escrow
    case AWAITING_HANDOVER = 'awaiting_handover';
    case AWAITING_CONFIRMATION = 'awaiting_confirmation';
    case DISPUTED = 'disputed';

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
            self::AWAITING_HANDOVER => 'Menunggu Serah Terima',
            self::AWAITING_CONFIRMATION => 'Menunggu Konfirmasi Pembeli',
            self::DISPUTED => 'Sengketa',
        };
    }

    /**
     * Status C2C di mana dana pembeli masih tertahan di akun escrow.
     */
    public function isEscrowHeld(): bool
    {
        return in_array($this, [
            self::AWAITING_HANDOVER,
            self::AWAITING_CONFIRMATION,
            self::DISPUTED,
        ], true);
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
            self::AWAITING_HANDOVER => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
            self::AWAITING_CONFIRMATION => 'bg-sky-500/10 text-sky-400 border-sky-500/20',
            self::DISPUTED => 'bg-orange-500/10 text-orange-400 border-orange-500/20',
        };
    }
}
