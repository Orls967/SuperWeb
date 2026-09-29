<?php

declare(strict_types=1);

namespace Modules\AutoServe\Domain\Enums;

enum EstimateStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return match ($this) {
            self::Draft => in_array($next, [self::Sent], true),
            self::Sent => in_array($next, [self::Approved, self::Rejected, self::Draft], true),
            self::Approved, self::Rejected => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf Estimasi',
            self::Sent => 'Menunggu Persetujuan Customer',
            self::Approved => 'Disetujui (Dana Ditahan)',
            self::Rejected => 'Ditolak Customer',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-500/15 text-slate-400 border-slate-500/30',
            self::Sent => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
            self::Approved => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
            self::Rejected => 'bg-rose-500/15 text-rose-400 border-rose-500/30',
        };
    }
}
