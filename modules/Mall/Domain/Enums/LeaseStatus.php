<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum LeaseStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case TERMINATED = 'terminated';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Konsep (Menunggu Deposit)',
            self::ACTIVE => 'Aktif Berjalan',
            self::SUSPENDED => 'Ditangguhkan (Tunggakan)',
            self::TERMINATED => 'Dihentikan (Terminasi)',
            self::EXPIRED => 'Selesai / Kedaluwarsa',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-amber-100 text-amber-800 border-amber-200',
            self::ACTIVE => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::SUSPENDED => 'bg-rose-100 text-rose-800 border-rose-200',
            self::TERMINATED => 'bg-slate-100 text-slate-800 border-slate-200',
            self::EXPIRED => 'bg-purple-100 text-purple-800 border-purple-200',
        };
    }
}
