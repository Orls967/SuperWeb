<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Enums;

enum MaintenanceVisitStatus: string
{
    case PLANNED = 'planned';
    case GROUNDED = 'grounded';
    case IN_WORK = 'in_work';
    case INSPECTION = 'inspection';
    case RELEASED_TO_SERVICE = 'released_to_service';
    case DEFERRED = 'deferred';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Terjadwal',
            self::GROUNDED => 'Pesawat Masuk Hanggar',
            self::IN_WORK => 'Dalam Pengerjaan MRO',
            self::INSPECTION => 'Uji NDT & Kelaikan',
            self::RELEASED_TO_SERVICE => 'RTS (Sertifikat Rilis)',
            self::DEFERRED => 'MEL Deferral Disetujui',
            self::CLOSED => 'Selesai & Ditutup',
        };
    }
}
