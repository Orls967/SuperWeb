<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Enums;

enum MilestoneStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Overdue = 'overdue';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::InProgress => 'Sedang Berjalan',
            self::Completed => 'Selesai',
            self::Overdue => 'Terlambat',
            self::Waived => 'Dilepaskan / Dimaafkan',
        };
    }
}
