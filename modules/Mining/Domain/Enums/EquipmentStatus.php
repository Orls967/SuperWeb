<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum EquipmentStatus: string
{
    case AVAILABLE = 'available';
    case ASSIGNED = 'assigned';
    case OPERATING = 'operating';
    case STANDBY = 'standby';
    case BREAKDOWN = 'breakdown';
    case UNDER_MAINTENANCE = 'under_maintenance';
    case RETURNED_TO_SERVICE = 'returned_to_service';
    case RETIRED = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Tersedia di Pool',
            self::ASSIGNED => 'Ditugaskan ke Shift',
            self::OPERATING => 'Beroperasi',
            self::STANDBY => 'Standby / Idle',
            self::BREAKDOWN => 'Rusak / Gangguan',
            self::UNDER_MAINTENANCE => 'Perawatan / Bengkel',
            self::RETURNED_TO_SERVICE => 'Lolos Uji Servis',
            self::RETIRED => 'Purnatugas (Decommissioned)',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::AVAILABLE => in_array($next, [self::ASSIGNED, self::STANDBY, self::BREAKDOWN, self::UNDER_MAINTENANCE, self::RETIRED], true),
            self::ASSIGNED => in_array($next, [self::OPERATING, self::STANDBY, self::BREAKDOWN, self::AVAILABLE], true),
            self::OPERATING => in_array($next, [self::STANDBY, self::BREAKDOWN, self::AVAILABLE, self::UNDER_MAINTENANCE], true),
            self::STANDBY => in_array($next, [self::OPERATING, self::BREAKDOWN, self::AVAILABLE, self::UNDER_MAINTENANCE], true),
            self::BREAKDOWN => in_array($next, [self::UNDER_MAINTENANCE, self::RETIRED], true),
            self::UNDER_MAINTENANCE => in_array($next, [self::RETURNED_TO_SERVICE, self::RETIRED], true),
            self::RETURNED_TO_SERVICE => in_array($next, [self::AVAILABLE, self::ASSIGNED], true),
            self::RETIRED => false,
        };
    }
}

