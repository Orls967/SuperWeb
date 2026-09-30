<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum DepositStatus: string
{
    case UNPAID = 'unpaid';
    case HELD = 'held';
    case PARTIALLY_APPLIED = 'partially_applied';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum Disetor',
            self::HELD => 'Ditahan (Deposit Aman)',
            self::PARTIALLY_APPLIED => 'Sebagian Digunakan untuk Tunggakan',
            self::REFUNDED => 'Dikembalikan Penuh',
        };
    }
}
