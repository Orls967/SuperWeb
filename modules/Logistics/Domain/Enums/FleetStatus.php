<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum FleetStatus: string
{
    case AVAILABLE = 'available';
    case ASSIGNED = 'assigned';
    case IN_TRANSIT = 'in_transit';
    case MAINTENANCE = 'maintenance';
    case RETIRED = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Tersedia (Available)',
            self::ASSIGNED => 'Ditugaskan (Assigned)',
            self::IN_TRANSIT => 'Dalam Perjalanan (In Transit)',
            self::MAINTENANCE => 'Perawatan (Maintenance)',
            self::RETIRED => 'Pensiun (Retired)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AVAILABLE => 'emerald',
            self::ASSIGNED => 'blue',
            self::IN_TRANSIT => 'amber',
            self::MAINTENANCE => 'rose',
            self::RETIRED => 'slate',
        };
    }

    /**
     * Memvalidasi transisi state machine armada.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return match ($this) {
            self::AVAILABLE => in_array($target, [self::ASSIGNED, self::MAINTENANCE, self::RETIRED]),
            self::ASSIGNED => in_array($target, [self::IN_TRANSIT, self::AVAILABLE, self::MAINTENANCE]),
            self::IN_TRANSIT => in_array($target, [self::AVAILABLE, self::MAINTENANCE]),
            self::MAINTENANCE => in_array($target, [self::AVAILABLE, self::RETIRED]),
            self::RETIRED => false,
        };
    }
}
