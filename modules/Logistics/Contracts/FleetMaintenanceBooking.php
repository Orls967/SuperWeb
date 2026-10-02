<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

/**
 * Contract for fleet service interval monitoring.
 *
 * Logistics dispatches FleetServiceDue event when a truck's odometer
 * exceeds the next service interval. AutoServe listens and creates
 * a booking via this contract.
 */
interface FleetMaintenanceBooking
{
    /**
     * Create a service booking for fleet maintenance.
     *
     * @return array{booking_id: int}
     */
    public function bookFleetService(int $vehicleId, int $odometerKm, string $reason): array;
}
