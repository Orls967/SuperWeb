<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum WorkOrderStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Terbuka / Menunggu Teknisi',
            self::IN_PROGRESS => 'Sedang Dikerjakan',
            self::ON_HOLD => 'Ditahan (Menunggu Sparepart)',
            self::COMPLETED => 'Selesai Dikerjakan',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
