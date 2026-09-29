<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Enums;

enum InstallmentStatus: string
{
    case Scheduled = 'scheduled';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return match ($this) {
            self::Scheduled => in_array($next, [self::Paid, self::Overdue], true),
            self::Overdue => in_array($next, [self::Paid], true),
            self::Paid => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal',
            self::Paid => 'Terbayar',
            self::Overdue => 'Terlambat',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Scheduled => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
            self::Paid => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            self::Overdue => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
        };
    }
}
