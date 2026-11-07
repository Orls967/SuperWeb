<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Services;

use Modules\Logistics\Contracts\EventShuttleFleetScheduler;

class EventShuttleFleetService implements EventShuttleFleetScheduler
{
    /**
     * Memeriksa dan mengalokasikan armada shuttle/bus penjemputan untuk bundle tiket.
     *
     * @return array{is_scheduled: bool, cost_per_seat_idr: int, schedule_code: string}
     */
    public function scheduleShuttleCapacity(
        string $routeCode,
        string $departureTime,
        int $passengerSeatsRequired
    ): array {
        $costPerSeat = 100000;
        $scheduleCode = 'SHT-'.strtoupper(substr(hash('sha256', $routeCode.$departureTime.(string) $passengerSeatsRequired), 0, 12));

        return [
            'is_scheduled' => true,
            'cost_per_seat_idr' => $costPerSeat,
            'schedule_code' => $scheduleCode,
        ];
    }
}
