<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Enums;

enum VehicleEventType: string
{
    case REGISTERED = 'registered';
    case ACQUIRED = 'acquired';
    case SERVICE_COMPLETED = 'service_completed';
    case PART_REPLACED = 'part_replaced';
    case ODOMETER_UPDATED = 'odometer_updated';
    case OWNERSHIP_TRANSFERRED = 'ownership_transferred';

    public function label(): string
    {
        return match ($this) {
            self::REGISTERED => 'Kendaraan Terdaftar',
            self::ACQUIRED => 'Kendaraan Diakuisisi',
            self::SERVICE_COMPLETED => 'Servis Berkala Selesai',
            self::PART_REPLACED => 'Penggantian Suku Cadang',
            self::ODOMETER_UPDATED => 'Pembaruan Odometer',
            self::OWNERSHIP_TRANSFERRED => 'Pengalihan Kepemilikan (C2C)',
        };
    }
}
