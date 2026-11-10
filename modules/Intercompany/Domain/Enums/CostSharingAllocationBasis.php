<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Enums;

enum CostSharingAllocationBasis: string
{
    case HEADCOUNT = 'HEADCOUNT';
    case REVENUE = 'REVENUE';
    case EQUAL_SHARE = 'EQUAL_SHARE';

    public function description(): string
    {
        return match ($this) {
            self::HEADCOUNT => 'Alokasi Berdasarkan Proporsi Jumlah Karyawan Aktif',
            self::REVENUE => 'Alokasi Berdasarkan Proporsi Pendapatan Kotor Tahunan',
            self::EQUAL_SHARE => 'Pembagian Rata Antar Seluruh Anak Perusahaan',
        };
    }
}
