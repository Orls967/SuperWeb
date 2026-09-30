<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum ScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Loading = 'loading';
    case Departed = 'departed';
    case Arrived = 'arrived';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal',
            self::Loading => 'Pemuatan Kargo',
            self::Departed => 'Dalam Perjalanan',
            self::Arrived => 'Tiba di Tujuan',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
