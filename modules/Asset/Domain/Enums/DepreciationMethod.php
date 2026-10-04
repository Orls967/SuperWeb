<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Enums;

enum DepreciationMethod: string
{
    case StraightLine = 'straight_line';
    case DecliningBalance = 'declining_balance';
    case UnitsOfProduction = 'units_of_production';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::StraightLine => 'Garis Lurus',
            self::DecliningBalance => 'Saldo Menurun',
            self::UnitsOfProduction => 'Unit Produksi',
            self::None => 'Tidak Disusutkan',
        };
    }
}
