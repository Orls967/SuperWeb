<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Enums;

enum FareClass: string
{
    case FLEX = 'FLEX';
    case STD = 'STD';
    case PROMO = 'PROMO';

    public function label(): string
    {
        return match ($this) {
            self::FLEX => 'Fleksibel Penuh',
            self::STD => 'Standar Reguler',
            self::PROMO => 'Promosi Hemat',
        };
    }
}
