<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum CateringStatus: string
{
    case INQUIRY = 'inquiry';
    case QUOTED = 'quoted';
    case CONFIRMED = 'confirmed';
    case COOKING = 'cooking';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::INQUIRY => 'Permintaan Baru',
            self::QUOTED => 'Penawaran Harga Diberikan',
            self::CONFIRMED => 'Terkonfirmasi (Deposit Ditahan)',
            self::COOKING => 'Dalam Proses Dapur',
            self::DELIVERED => 'Terkirim ke Lokasi',
            self::COMPLETED => 'Selesai & Lunas',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
