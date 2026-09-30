<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum SalesReportSource: string
{
    case MANUAL = 'manual';
    case INTEGRATED = 'integrated';
    case POS = 'pos';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Laporan Manual Tenant',
            self::INTEGRATED => 'Integrasi Sistem Otomatis',
            self::POS => 'Sinkronisasi POS',
        };
    }
}
