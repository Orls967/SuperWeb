<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentEvent;
use RuntimeException;

final class TicketTierCapacityAllocationService
{
    public const SAFETY_HEADROOM_RESERVE_BPS = 1000; // 10% cadangan keselamatan kapasitas

    /**
     * Allocate ticket tier quotas ensuring legal venue capacity and safety headroom reserves are respected.
     *
     * @param  array<string, int>  $tierQuotas
     * @return array{
     *     event_code: string,
     *     legal_capacity: int,
     *     safety_headroom_reserve: int,
     *     max_saleable_capacity: int,
     *     allocated_tiers: array<string, int>,
     *     total_allocated: int,
     *     is_valid: bool
     * }
     */
    public function allocateTierQuotas(EntertainmentEvent $event, array $tierQuotas): array
    {
        $legalCapacity = (int) ($event->max_capacity ?? 1000);
        $headroom = (int) floor(($legalCapacity * self::SAFETY_HEADROOM_RESERVE_BPS) / 10000);
        $maxSaleable = max(0, $legalCapacity - $headroom);

        $totalAllocated = array_sum($tierQuotas);
        if ($totalAllocated > $maxSaleable) {
            throw new RuntimeException("Total alokasi tiket ({$totalAllocated}) melebihi kuota kapasitas aman yang dapat dijual ({$maxSaleable}).");
        }

        return [
            'event_code' => $event->event_code,
            'legal_capacity' => $legalCapacity,
            'safety_headroom_reserve' => $headroom,
            'max_saleable_capacity' => $maxSaleable,
            'allocated_tiers' => $tierQuotas,
            'total_allocated' => $totalAllocated,
            'is_valid' => true,
        ];
    }
}
