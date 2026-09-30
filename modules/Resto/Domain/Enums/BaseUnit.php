<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum BaseUnit: string
{
    case GRAM = 'gram';
    case ML = 'ml';
    case PCS = 'pcs';

    public function label(): string
    {
        return match ($this) {
            self::GRAM => 'Gram (g)',
            self::ML => 'Mililiter (ml)',
            self::PCS => 'Pieces / Butir / Pcs',
        };
    }
}
