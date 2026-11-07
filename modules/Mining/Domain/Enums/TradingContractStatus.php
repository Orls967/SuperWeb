<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum TradingContractStatus: string
{
    case DRAFT = 'DRAFT';
    case PRICE_LOCKED = 'PRICE_LOCKED';
    case ACTIVE_HEDGED = 'ACTIVE_HEDGED';
    case MARGIN_CALLED = 'MARGIN_CALLED';
    case SETTLEMENT_PENDING = 'SETTLEMENT_PENDING';
    case PHYSICAL_DELIVERED = 'PHYSICAL_DELIVERED';
    case FINANCIALLY_SETTLED = 'FINANCIALLY_SETTLED';
    case TERMINATED = 'TERMINATED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf Kontrak Perdagangan Logam',
            self::PRICE_LOCKED => 'Harga LME Terkunci',
            self::ACTIVE_HEDGED => 'Posisi Lindung Nilai Aktif',
            self::MARGIN_CALLED => 'Panggilan Margin Variasi Aktif',
            self::SETTLEMENT_PENDING => 'Menunggu Penyelesaian Akhir',
            self::PHYSICAL_DELIVERED => 'Serah Fisik Logam Selesai',
            self::FINANCIALLY_SETTLED => 'Penyelesaian Finansial Lunas',
            self::TERMINATED => 'Kontrak Dihentikan Lebih Awal',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-gray-100 text-gray-800',
            self::PRICE_LOCKED => 'bg-blue-100 text-blue-800',
            self::ACTIVE_HEDGED => 'bg-indigo-100 text-indigo-800',
            self::MARGIN_CALLED => 'bg-amber-100 text-amber-800',
            self::SETTLEMENT_PENDING => 'bg-purple-100 text-purple-800',
            self::PHYSICAL_DELIVERED => 'bg-cyan-100 text-cyan-800',
            self::FINANCIALLY_SETTLED => 'bg-emerald-100 text-emerald-800',
            self::TERMINATED => 'bg-rose-100 text-rose-800',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT => in_array($target, [self::PRICE_LOCKED, self::TERMINATED], true),
            self::PRICE_LOCKED => in_array($target, [self::ACTIVE_HEDGED, self::SETTLEMENT_PENDING, self::PHYSICAL_DELIVERED, self::TERMINATED], true),
            self::ACTIVE_HEDGED => in_array($target, [self::MARGIN_CALLED, self::SETTLEMENT_PENDING, self::PHYSICAL_DELIVERED, self::TERMINATED], true),
            self::MARGIN_CALLED => in_array($target, [self::ACTIVE_HEDGED, self::TERMINATED], true),
            self::SETTLEMENT_PENDING => in_array($target, [self::PHYSICAL_DELIVERED, self::FINANCIALLY_SETTLED, self::TERMINATED], true),
            self::PHYSICAL_DELIVERED => in_array($target, [self::FINANCIALLY_SETTLED], true),
            self::FINANCIALLY_SETTLED => false,
            self::TERMINATED => false,
        };
    }
}
