<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum WarehouseReceiptStatus: string
{
    case ISSUED = 'ISSUED';
    case VERIFIED_ASSAY = 'VERIFIED_ASSAY';
    case REGISTERED_BAPPEBTI = 'REGISTERED_BAPPEBTI';
    case PLEDGED_SCF = 'PLEDGED_SCF';
    case PARTIAL_RELEASE = 'PARTIAL_RELEASE';
    case FULLY_RELEASED = 'FULLY_RELEASED';
    case DEFAULTED = 'DEFAULTED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Resi Diterbitkan Pengelola Gudang',
            self::VERIFIED_ASSAY => 'Kadar Mutu Terverifikasi Lab',
            self::REGISTERED_BAPPEBTI => 'Terdaftar Resmi di Bappebti / ISWARE',
            self::PLEDGED_SCF => 'Dijaminkan Pembiayaan SCF Bank',
            self::PARTIAL_RELEASE => 'Pelepasan Sebagian Kolateral',
            self::FULLY_RELEASED => 'Pelepasan Penuh (Pelunasan SCF)',
            self::DEFAULTED => 'Kredit Macet / Eksekusi Agunan',
            self::CANCELLED => 'Resi Gudang Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ISSUED => 'bg-gray-100 text-gray-800',
            self::VERIFIED_ASSAY => 'bg-blue-100 text-blue-800',
            self::REGISTERED_BAPPEBTI => 'bg-indigo-100 text-indigo-800',
            self::PLEDGED_SCF => 'bg-amber-100 text-amber-800',
            self::PARTIAL_RELEASE => 'bg-purple-100 text-purple-800',
            self::FULLY_RELEASED => 'bg-emerald-100 text-emerald-800',
            self::DEFAULTED => 'bg-rose-100 text-rose-800',
            self::CANCELLED => 'bg-zinc-100 text-zinc-800',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::ISSUED => in_array($target, [self::VERIFIED_ASSAY, self::CANCELLED], true),
            self::VERIFIED_ASSAY => in_array($target, [self::REGISTERED_BAPPEBTI, self::CANCELLED], true),
            self::REGISTERED_BAPPEBTI => in_array($target, [self::PLEDGED_SCF, self::CANCELLED], true),
            self::PLEDGED_SCF => in_array($target, [self::PARTIAL_RELEASE, self::FULLY_RELEASED, self::DEFAULTED], true),
            self::PARTIAL_RELEASE => in_array($target, [self::PARTIAL_RELEASE, self::FULLY_RELEASED, self::DEFAULTED], true),
            self::FULLY_RELEASED => false,
            self::DEFAULTED => false,
            self::CANCELLED => false,
        };
    }
}
