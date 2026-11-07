<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Enums;

enum EventStatus: string
{
    case DRAFT = 'draft';
    case PERMIT_PENDING = 'permit_pending';
    case ON_SALE = 'on_sale';
    case SOLD_OUT = 'sold_out';
    case LIVE = 'live';
    case CLOSED = 'closed';
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf Rencana',
            self::PERMIT_PENDING => 'Menunggu Izin Polisi',
            self::ON_SALE => 'Tiket Dijual',
            self::SOLD_OUT => 'Tiket Habis',
            self::LIVE => 'Pintu Dibuka (Live)',
            self::CLOSED => 'Acara Ditutup',
            self::SETTLED => 'Terekonsiliasi Penuh',
            self::CANCELLED => 'Dibatalkan Darurat',
        };
    }
}
