<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Enums;

enum SpecimenCustodyStatus: string
{
    case COLLECTED = 'COLLECTED';
    case IN_TRANSIT = 'IN_TRANSIT';
    case RECEIVED_LAB = 'RECEIVED_LAB';
    case PROCESSED = 'PROCESSED';
    case REJECTED = 'REJECTED';
    case DISPOSED = 'DISPOSED';

    public function label(): string
    {
        return match ($this) {
            self::COLLECTED => 'Spesimen Diambil di Ruangan',
            self::IN_TRANSIT => 'Dalam Pengiriman Kurir Berpendingin',
            self::RECEIVED_LAB => 'Diterima di Meja Triase Lab Pusat',
            self::PROCESSED => 'Selesai Dianalisis Instrumen',
            self::REJECTED => 'Spesimen Ditolak (Pelanggaran Suhu / Rusak)',
            self::DISPOSED => 'Dimusnahkan Sesuai SOP Limbah Medis B3',
        };
    }
}
