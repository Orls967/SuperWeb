<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum WorkPermitStatus: string
{
    case REQUESTED = 'requested';
    case RISK_ASSESSED = 'risk_assessed';
    case APPROVED = 'approved';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case EXPIRED = 'expired';
    case CLOSED = 'closed';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => 'Diajukan',
            self::RISK_ASSESSED => 'Penilaian Risiko K3',
            self::APPROVED => 'Disetujui Pengawas',
            self::ACTIVE => 'Aktif di Lapangan',
            self::SUSPENDED => 'Ditangguhkan (Stop Work)',
            self::EXPIRED => 'Kedaluwarsa',
            self::CLOSED => 'Selesai Aman',
            self::REJECTED => 'Ditolak K3',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::REQUESTED => 'bg-gray-100 text-gray-800',
            self::RISK_ASSESSED => 'bg-blue-100 text-blue-800',
            self::APPROVED => 'bg-indigo-100 text-indigo-800',
            self::ACTIVE => 'bg-emerald-100 text-emerald-800',
            self::SUSPENDED => 'bg-amber-100 text-amber-800',
            self::EXPIRED => 'bg-red-100 text-red-800',
            self::CLOSED => 'bg-purple-100 text-purple-800',
            self::REJECTED => 'bg-rose-100 text-rose-800',
        };
    }

    public function canWork(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::REQUESTED => in_array($target, [self::RISK_ASSESSED, self::REJECTED, self::REQUESTED], true),
            self::RISK_ASSESSED => in_array($target, [self::APPROVED, self::REJECTED], true),
            self::APPROVED => in_array($target, [self::ACTIVE, self::SUSPENDED, self::REJECTED], true),
            self::ACTIVE => in_array($target, [self::SUSPENDED, self::EXPIRED, self::CLOSED], true),
            self::SUSPENDED => in_array($target, [self::ACTIVE, self::CLOSED, self::REJECTED], true),
            self::EXPIRED => in_array($target, [self::CLOSED], true),
            self::CLOSED, self::REJECTED => false,
        };
    }
}
