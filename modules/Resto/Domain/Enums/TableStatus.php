<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum TableStatus: string
{
    case AVAILABLE = 'available';
    case OCCUPIED = 'occupied';
    case RESERVED = 'reserved';
    case CLEANING = 'cleaning';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Kosong / Tersedia',
            self::OCCUPIED => 'Terisi / Sedang Makan',
            self::RESERVED => 'Dipesan (Reserved)',
            self::CLEANING => 'Sedang Dibersihkan',
        };
    }
}
