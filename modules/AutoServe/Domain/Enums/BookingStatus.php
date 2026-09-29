<?php

declare(strict_types=1);

namespace Modules\AutoServe\Domain\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case WaitingParts = 'waiting_parts';
    case Completed = 'completed';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public static function fromString(string $value): self
    {
        if ($value === 'assigned') {
            return self::Confirmed;
        }

        return self::from($value);
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        if ($value === 'assigned') {
            return self::Confirmed;
        }

        return self::tryFrom($value);
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return match ($this) {
            self::Pending => in_array($next, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => in_array($next, [self::InProgress, self::Cancelled], true),
            self::InProgress => in_array($next, [self::WaitingParts, self::Completed, self::Cancelled], true),
            self::WaitingParts => in_array($next, [self::InProgress, self::Cancelled], true),
            self::Completed => in_array($next, [self::Invoiced], true),
            self::Invoiced, self::Cancelled => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Konfirmasi',
            self::Confirmed => 'Terkonfirmasi (Mekanik Ditugaskan)',
            self::InProgress => 'Sedang Dikerjakan',
            self::WaitingParts => 'Menunggu Sparepart',
            self::Completed => 'Selesai',
            self::Invoiced => 'Invoice Dibuat',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
            self::Confirmed => 'bg-blue-500/15 text-blue-400 border-blue-500/30',
            self::InProgress => 'bg-purple-500/15 text-purple-400 border-purple-500/30',
            self::WaitingParts => 'bg-orange-500/15 text-orange-400 border-orange-500/30',
            self::Completed => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
            self::Invoiced => 'bg-cyan-500/15 text-cyan-400 border-cyan-500/30',
            self::Cancelled => 'bg-rose-500/15 text-rose-400 border-rose-500/30',
        };
    }
}
