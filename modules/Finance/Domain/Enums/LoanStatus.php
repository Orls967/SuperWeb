<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Enums;

enum LoanStatus: string
{
    case Active = 'active';
    case MarginCall = 'margin_call';
    case Liquidated = 'liquidated';
    case PaidOff = 'paid_off';
    case Defaulted = 'defaulted';

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return match ($this) {
            self::Active => in_array($next, [self::MarginCall, self::PaidOff, self::Liquidated, self::Defaulted], true),
            self::MarginCall => in_array($next, [self::Active, self::Liquidated, self::PaidOff, self::Defaulted], true),
            self::Liquidated, self::PaidOff, self::Defaulted => false,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Active, self::MarginCall], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::MarginCall => 'Margin Call',
            self::Liquidated => 'Dilikuidasi',
            self::PaidOff => 'Lunas',
            self::Defaulted => 'Gagal Bayar',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            self::MarginCall => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            self::Liquidated => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
            self::PaidOff => 'bg-sky-500/10 text-sky-400 border-sky-500/20',
            self::Defaulted => 'bg-red-500/10 text-red-400 border-red-500/20',
        };
    }
}
