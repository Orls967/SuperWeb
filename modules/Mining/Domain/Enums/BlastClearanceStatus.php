<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum BlastClearanceStatus: string
{
    case DRAFT = 'DRAFT';
    case RADIUS_CALCULATED = 'RADIUS_CALCULATED';
    case PERMITS_VERIFIED = 'PERMITS_VERIFIED';
    case SIREN_ACTIVE = 'SIREN_ACTIVE';
    case ALL_CLEAR = 'ALL_CLEAR';
    case DETONATED = 'DETONATED';
    case MUCKPILE_INSPECTED = 'MUCKPILE_INSPECTED';
    case RESOLVED = 'RESOLVED';
    case BLOCKED = 'BLOCKED';
    case ABORTED = 'ABORTED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Perancangan Peledakan',
            self::RADIUS_CALCULATED => 'Radius Bahaya Dihitung',
            self::PERMITS_VERIFIED => 'Izin K3 & Detonator Sah',
            self::SIREN_ACTIVE => 'Sirene Bahaya Aktif',
            self::ALL_CLEAR => 'Perimeter 100% Steril',
            self::DETONATED => 'Peledakan Berhasil',
            self::MUCKPILE_INSPECTED => 'Inspeksi Timbunan Muckpile',
            self::RESOLVED => 'Area Dinyatakan Aman',
            self::BLOCKED => 'Diblokir Interlock Keselamatan',
            self::ABORTED => 'Dibatalkan Pengawas Peledakan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-gray-100 text-gray-800',
            self::RADIUS_CALCULATED => 'bg-blue-100 text-blue-800',
            self::PERMITS_VERIFIED => 'bg-indigo-100 text-indigo-800',
            self::SIREN_ACTIVE => 'bg-amber-100 text-amber-800',
            self::ALL_CLEAR => 'bg-teal-100 text-teal-800',
            self::DETONATED => 'bg-purple-100 text-purple-800',
            self::MUCKPILE_INSPECTED => 'bg-cyan-100 text-cyan-800',
            self::RESOLVED => 'bg-emerald-100 text-emerald-800',
            self::BLOCKED => 'bg-red-100 text-red-800',
            self::ABORTED => 'bg-rose-100 text-rose-800',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT => in_array($target, [self::RADIUS_CALCULATED, self::PERMITS_VERIFIED, self::SIREN_ACTIVE, self::ALL_CLEAR, self::ABORTED], true),
            self::RADIUS_CALCULATED => in_array($target, [self::PERMITS_VERIFIED, self::SIREN_ACTIVE, self::ALL_CLEAR, self::BLOCKED, self::ABORTED], true),
            self::PERMITS_VERIFIED => in_array($target, [self::SIREN_ACTIVE, self::ALL_CLEAR, self::BLOCKED, self::ABORTED], true),
            self::SIREN_ACTIVE => in_array($target, [self::ALL_CLEAR, self::DETONATED, self::BLOCKED, self::ABORTED], true),
            self::ALL_CLEAR => in_array($target, [self::SIREN_ACTIVE, self::DETONATED, self::BLOCKED, self::ABORTED], true),
            self::DETONATED => in_array($target, [self::MUCKPILE_INSPECTED], true),
            self::MUCKPILE_INSPECTED => in_array($target, [self::RESOLVED], true),
            self::BLOCKED => in_array($target, [self::SIREN_ACTIVE, self::ABORTED], true),
            self::RESOLVED, self::ABORTED => false,
        };
    }
}
