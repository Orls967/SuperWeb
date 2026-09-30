<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum IngredientCategory: string
{
    case PROTEIN = 'protein';
    case SAYUR = 'sayur';
    case BUMBU = 'bumbu';
    case BERAS = 'beras';
    case MINUMAN = 'minuman';
    case KEMASAN = 'kemasan';

    public function label(): string
    {
        return match ($this) {
            self::PROTEIN => 'Protein / Daging / Ikan',
            self::SAYUR => 'Sayuran Segar',
            self::BUMBU => 'Bumbu & Rempah',
            self::BERAS => 'Beras & Padi-padian',
            self::MINUMAN => 'Bahan Minuman',
            self::KEMASAN => 'Bahan Kemasan / Bungkus',
        };
    }
}
