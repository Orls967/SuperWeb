<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Enums;

enum AviationCabin: string
{
    case Y = 'Y';
    case J = 'J';

    public function label(): string
    {
        return match ($this) {
            self::Y => 'Ekonomi (Y)',
            self::J => 'Bisnis (J)',
        };
    }
}

