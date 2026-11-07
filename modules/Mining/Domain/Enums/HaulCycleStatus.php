<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum HaulCycleStatus: string
{
    case QUEUED = 'queued';
    case ASSIGNED = 'assigned';
    case TRAVELLING_EMPTY = 'travelling_empty';
    case LOADING = 'loading';
    case HAULING = 'hauling';
    case DUMPING = 'dumping';
    case COMPLETED = 'completed';
    case ABORTED = 'aborted';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'Dalam Antrean',
            self::ASSIGNED => 'Ditugaskan',
            self::TRAVELLING_EMPTY => 'Perjalanan Kosong',
            self::LOADING => 'Pemuatan Material',
            self::HAULING => 'Perjalanan Bermuatan',
            self::DUMPING => 'Pembongkaran',
            self::COMPLETED => 'Selesai Siklus',
            self::ABORTED => 'Dibatalkan / Insiden',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::QUEUED => in_array($next, [self::ASSIGNED, self::ABORTED], true),
            self::ASSIGNED => in_array($next, [self::TRAVELLING_EMPTY, self::ABORTED], true),
            self::TRAVELLING_EMPTY => in_array($next, [self::LOADING, self::ABORTED], true),
            self::LOADING => in_array($next, [self::HAULING, self::ABORTED], true),
            self::HAULING => in_array($next, [self::DUMPING, self::ABORTED], true),
            self::DUMPING => in_array($next, [self::COMPLETED, self::ABORTED], true),
            self::COMPLETED, self::ABORTED => false,
        };
    }
}
