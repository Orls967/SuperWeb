<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Enums;

/**
 * Kategori aset default (PSAK 16 — simulasi).
 * Umur ekonomis & metode penyusutan adalah asumsi simulasi.
 */
enum AssetCategoryCode: string
{
    case Land = 'land';
    case Building = 'building';
    case FactoryMachine = 'factory_machine';
    case Vehicle = 'vehicle';
    case Equipment = 'equipment';
    case It = 'it';
    case Intangible = 'intangible';

    public function label(): string
    {
        return match ($this) {
            self::Land => 'Tanah',
            self::Building => 'Bangunan',
            self::FactoryMachine => 'Mesin Pabrik',
            self::Vehicle => 'Kendaraan',
            self::Equipment => 'Peralatan',
            self::It => 'Peralatan IT',
            self::Intangible => 'Hak / Intangible',
        };
    }

    public function usefulLifeYears(): int
    {
        return match ($this) {
            self::Land => 0, // Tanah tidak disusutkan
            self::Building => 20,
            self::FactoryMachine => 10,
            self::Vehicle => 5,
            self::Equipment => 8,
            self::It => 4,
            self::Intangible => 5,
        };
    }

    public function depreciationMethod(): string
    {
        return match ($this) {
            self::Land => 'none',
            self::Building, self::FactoryMachine, self::Vehicle, self::It => 'straight_line',
            self::Equipment => 'declining_balance',
            self::Intangible => 'straight_line',
        };
    }
}
