<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum RentModel: string
{
    case FIXED = 'fixed';
    case REVENUE_SHARE = 'revenue_share';
    case GREATER_OF = 'greater_of';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Tarif Tetap (Fixed Rate)',
            self::REVENUE_SHARE => 'Bagi Hasil Omzet (Pure Rev Share)',
            self::GREATER_OF => 'Nilai Tertinggi (Greater of Fixed or Rev Share)',
        };
    }
}
