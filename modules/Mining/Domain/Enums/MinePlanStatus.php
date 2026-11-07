<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum MinePlanStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case REVIEWED = 'reviewed';
    case APPROVED = 'approved';
    case ACTIVE = 'active';
    case REVISED = 'revised';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Konsep Rencana',
            self::SUBMITTED => 'Diajukan',
            self::REVIEWED => 'Ditelaah Teknis',
            self::APPROVED => 'Disetujui',
            self::ACTIVE => 'Aktif Berjalan',
            self::REVISED => 'Direvisi (Versi Baru)',
            self::CLOSED => 'Selesai Periode',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::DRAFT => in_array($next, [self::SUBMITTED, self::CANCELLED], true),
            self::SUBMITTED => in_array($next, [self::REVIEWED, self::CANCELLED], true),
            self::REVIEWED => in_array($next, [self::APPROVED, self::DRAFT, self::CANCELLED], true),
            self::APPROVED => in_array($next, [self::ACTIVE, self::CANCELLED], true),
            self::ACTIVE => in_array($next, [self::REVISED, self::CLOSED], true),
            self::REVISED => in_array($next, [self::ACTIVE, self::CLOSED], true),
            self::CLOSED, self::CANCELLED => false,
        };
    }
}
