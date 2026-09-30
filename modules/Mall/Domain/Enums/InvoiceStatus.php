<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Konsep',
            self::ISSUED => 'Diterbitkan (Belum Lunas)',
            self::PARTIALLY_PAID => 'Dibayar Sebagian',
            self::PAID => 'Lunas',
            self::OVERDUE => 'Jatuh Tempo (Tertunggak)',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-slate-100 text-slate-700 border-slate-200',
            self::ISSUED => 'bg-blue-100 text-blue-800 border-blue-200',
            self::PARTIALLY_PAID => 'bg-amber-100 text-amber-800 border-amber-200',
            self::PAID => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::OVERDUE => 'bg-rose-100 text-rose-800 border-rose-200',
            self::CANCELLED => 'bg-gray-100 text-gray-500 border-gray-200',
        };
    }
}
