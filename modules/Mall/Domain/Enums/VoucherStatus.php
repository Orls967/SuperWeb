<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum VoucherStatus: string
{
    case ACTIVE = 'active';
    case USED = 'used';
    case EXPIRED = 'expired';
    case SETTLED = 'settled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif / Siap Pakai',
            self::USED => 'Sudah Digunakan',
            self::EXPIRED => 'Kedaluwarsa',
            self::SETTLED => 'Sudah Di-settle ke Tenant',
        };
    }
}
