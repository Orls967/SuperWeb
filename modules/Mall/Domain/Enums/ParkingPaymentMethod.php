<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum ParkingPaymentMethod: string
{
    case CASH = 'cash';
    case WALLET = 'wallet';
    case MEMBER_FREE = 'member_free';
    case TENANT_FREE = 'tenant_free';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Tunai di Gate',
            self::WALLET => 'Dompet Digital AutoServe',
            self::MEMBER_FREE => 'Bebas Parkir Member',
            self::TENANT_FREE => 'Validasi Belanja Tenant',
        };
    }
}
