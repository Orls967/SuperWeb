<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum POStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf PO',
            self::SENT => 'Terkirim ke Supplier',
            self::PARTIALLY_RECEIVED => 'Diterima Sebagian',
            self::RECEIVED => 'Diterima Penuh',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
