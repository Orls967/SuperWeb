<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum SessionStatus: string
{
    case OPEN = 'open';
    case CLOSING = 'closing';
    case CLOSED = 'closed';
    case ABANDONED = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Sesi Aktif',
            self::CLOSING => 'Hitung Hidangan / Kasir',
            self::CLOSED => 'Selesai & Lunas',
            self::ABANDONED => 'Dibatalkan',
        };
    }
}
