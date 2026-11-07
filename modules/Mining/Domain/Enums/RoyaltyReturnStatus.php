<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum RoyaltyReturnStatus: string
{
    case DRAFT = 'draft';
    case CALCULATED = 'calculated';
    case REVIEWED = 'reviewed';
    case APPROVED = 'approved';
    case FILED = 'filed';
    case PAID = 'paid';
    case AMENDED = 'amended';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf SPT',
            self::CALCULATED => 'Dihitung Progresif',
            self::REVIEWED => 'Ditinjau Finance',
            self::APPROVED => 'Disetujui Direksi',
            self::FILED => 'Dilaporkan ke SIMPONI',
            self::PAID => 'Lunas NTPN',
            self::AMENDED => 'SPT Pembetulan',
            self::CLOSED => 'Selesai / Arsip',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-gray-100 text-gray-800',
            self::CALCULATED, self::REVIEWED => 'bg-blue-100 text-blue-800',
            self::APPROVED => 'bg-amber-100 text-amber-800',
            self::FILED => 'bg-indigo-100 text-indigo-800',
            self::PAID => 'bg-emerald-100 text-emerald-800',
            self::AMENDED => 'bg-orange-100 text-orange-800',
            self::CLOSED => 'bg-purple-100 text-purple-800',
        };
    }

    public function isPaidOrClosed(): bool
    {
        return $this === self::PAID || $this === self::CLOSED;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT => in_array($target, [self::CALCULATED, self::DRAFT], true),
            self::CALCULATED => in_array($target, [self::REVIEWED, self::DRAFT], true),
            self::REVIEWED => in_array($target, [self::APPROVED, self::CALCULATED], true),
            self::APPROVED => in_array($target, [self::FILED, self::REVIEWED], true),
            self::FILED => in_array($target, [self::PAID, self::AMENDED], true),
            self::PAID => in_array($target, [self::CLOSED, self::AMENDED], true),
            self::AMENDED => in_array($target, [self::CALCULATED, self::REVIEWED, self::DRAFT], true),
            self::CLOSED => false,
        };
    }
}
