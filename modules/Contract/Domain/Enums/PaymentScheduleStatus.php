<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Enums;

enum PaymentScheduleStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum Dibayar',
            self::Partial => 'Dibayar Sebagian',
            self::Paid => 'Lunas',
            self::Waived => 'Dibebaskan',
        };
    }
}
