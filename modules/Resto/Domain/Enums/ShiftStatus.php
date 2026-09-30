<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum ShiftStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case REVIEWED = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aktif / Terbuka',
            self::CLOSED => 'Ditutup',
            self::REVIEWED => 'Ditinjau Manager',
        };
    }
}
