<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

interface EventShuttleFleetScheduler
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
    ): array;
}
