<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum LocationType: string
{
    case SEAPORT = 'seaport';
    case AIRPORT = 'airport';
    case HUB = 'hub';
    case DEPOT = 'depot';
    case WAREHOUSE = 'warehouse';
    case CFS = 'cfs';
    case CUSTOMER_POINT = 'customer_point';

    public function label(): string
    {
        return match ($this) {
            self::SEAPORT => 'Pelabuhan Laut (Seaport)',
            self::AIRPORT => 'Bandar Udara (Airport)',
            self::HUB => 'Hub Distribusi Darat',
            self::DEPOT => 'Depot Kontainer',
            self::WAREHOUSE => 'Gudang (Warehouse)',
            self::CFS => 'Container Freight Station (CFS)',
            self::CUSTOMER_POINT => 'Titik Konsumen / Shipper',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SEAPORT => 'cyan',
            self::AIRPORT => 'violet',
            self::HUB => 'amber',
            self::DEPOT => 'emerald',
            self::WAREHOUSE => 'blue',
            self::CFS => 'indigo',
            self::CUSTOMER_POINT => 'rose',
        };
    }
}
