<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Enums;

enum PaymentScheduleKind: string
{
    case Advance = 'advance';
    case Term = 'term';
    case Periodic = 'periodic';
    case Milestone = 'milestone';
    case Retention = 'retention';

    public function label(): string
    {
        return match ($this) {
            self::Advance => 'Uang Muka',
            self::Term => 'Termin',
            self::Periodic => 'Berkala',
            self::Milestone => 'Berdasarkan Milestone',
            self::Retention => 'Retensi',
        };
    }
}
