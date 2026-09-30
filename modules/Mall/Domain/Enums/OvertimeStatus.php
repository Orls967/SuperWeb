<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum OvertimeStatus: string
{
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case BILLED = 'billed';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => 'Menunggu Persetujuan',
            self::APPROVED => 'Disetujui (Siap Ditagih)',
            self::REJECTED => 'Ditolak',
            self::BILLED => 'Sudah Masuk Tagihan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::REQUESTED => 'bg-amber-100 text-amber-800 border-amber-200',
            self::APPROVED => 'bg-blue-100 text-blue-800 border-blue-200',
            self::REJECTED => 'bg-rose-100 text-rose-800 border-rose-200',
            self::BILLED => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        };
    }
}
