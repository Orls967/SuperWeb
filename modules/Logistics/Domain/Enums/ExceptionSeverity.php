<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum ExceptionSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Medium => 'Sedang',
            self::High => 'Tinggi',
            self::Critical => 'Kritis',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Low => 'bg-slate-700/60 text-slate-300 border-slate-600',
            self::Medium => 'bg-amber-900/50 text-amber-300 border-amber-700',
            self::High => 'bg-orange-900/50 text-orange-300 border-orange-700',
            self::Critical => 'bg-rose-900/60 text-rose-300 border-rose-600',
        };
    }
}
