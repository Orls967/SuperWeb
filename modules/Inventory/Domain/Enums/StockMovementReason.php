<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Enums;

enum StockMovementReason: string
{
    case INITIAL = 'initial';
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case SERVICE_USAGE = 'service_usage';
    case ADJUSTMENT = 'adjustment';
    case RETURN = 'return';
    case RESERVATION = 'reservation';
    case RESERVATION_RELEASE = 'reservation_release';

    public function label(): string
    {
        return match ($this) {
            self::INITIAL => 'Stok Awal',
            self::PURCHASE => 'Pembelian / Restok',
            self::SALE => 'Penjualan',
            self::SERVICE_USAGE => 'Pemakaian Bengkel',
            self::ADJUSTMENT => 'Penyesuaian Manual',
            self::RETURN => 'Pengembalian / Retur',
            self::RESERVATION => 'Reservasi Checkout',
            self::RESERVATION_RELEASE => 'Pelepasan Reservasi',
        };
    }
}
