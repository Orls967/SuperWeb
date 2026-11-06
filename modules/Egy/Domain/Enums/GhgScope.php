<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Enums;

/**
 * Klasifikasi Cakupan Gas Rumah Kaca sesuai protokol ISO 14064-1 dan GHG Protocol.
 */
enum GhgScope: string
{
    case SCOPE_1_DIRECT = 'SCOPE_1_DIRECT';
    case SCOPE_2_LOCATION = 'SCOPE_2_LOCATION';
    case SCOPE_2_MARKET = 'SCOPE_2_MARKET';
    case SCOPE_3_UPSTREAM = 'SCOPE_3_UPSTREAM';
    case SCOPE_3_DOWNSTREAM = 'SCOPE_3_DOWNSTREAM';

    public function description(): string
    {
        return match ($this) {
            self::SCOPE_1_DIRECT => 'Emisi langsung dari pembakaran stasioner & bergerak (genset, boiler, armada utilitas)',
            self::SCOPE_2_LOCATION => 'Emisi tidak langsung dari jaringan listrik berbasis rata-rata faktor emisi grid nasional (PLN)',
            self::SCOPE_2_MARKET => 'Emisi listrik kontraktual memperhitungkan pengurang sertifikat energi terbarukan (REC)',
            self::SCOPE_3_UPSTREAM => 'Emisi rantai pasok hulu, pengolahan air baku, dan bahan kimia pengolahan',
            self::SCOPE_3_DOWNSTREAM => 'Emisi penggunaan energi hilir oleh penyewa mall, tamu hotel, dan pembuangan efluen',
        };
    }
}
