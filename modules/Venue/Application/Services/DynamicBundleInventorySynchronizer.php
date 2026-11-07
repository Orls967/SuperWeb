<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Hotel\Contracts\HotelPackageAvailabilityValidator;
use Modules\Logistics\Contracts\EventShuttleFleetScheduler;
use Modules\Venue\Domain\Models\DynamicBundlePackage;

final class DynamicBundleInventorySynchronizer
{
    public function __construct(
        private readonly ?HotelPackageAvailabilityValidator $hotelValidator = null,
        private readonly ?EventShuttleFleetScheduler $shuttleScheduler = null
    ) {}

    /**
     * Sinkronisasi status ketersediaan inventaris lintas modul (Hotel & Logistik).
     *
     * @return array{
     *   package_id: int,
     *   is_fully_available: bool,
     *   hotel_status: string,
     *   shuttle_status: string
     * }
     */
    public function checkPackageReadiness(DynamicBundlePackage $package): array
    {
        $hotelStatus = 'NOT_APPLICABLE';
        $shuttleStatus = 'NOT_APPLICABLE';
        $isAvailable = true;

        if ($package->hotel_package_id !== null) {
            if ($this->hotelValidator !== null) {
                $checkIn = new \DateTimeImmutable('tomorrow');
                $checkOut = $checkIn->modify('+2 days');
                $hotelOk = $this->hotelValidator->validateAvailability($package->hotel_package_id, $checkIn, $checkOut, 1);
                $hotelStatus = $hotelOk ? 'AVAILABLE' : 'SOLDOUT';
                if (! $hotelOk) {
                    $isAvailable = false;
                }
            } else {
                $hotelStatus = 'SERVICE_UNAVAILABLE';
            }
        }

        if ($package->shuttle_route_id !== null) {
            if ($this->shuttleScheduler !== null) {
                $departure = new \DateTimeImmutable('tomorrow 16:00:00');
                $shuttleOk = $this->shuttleScheduler->scheduleEventShuttle(
                    $package->event_id,
                    $package->shuttle_route_id,
                    1,
                    $departure
                );
                $shuttleStatus = $shuttleOk ? 'CONFIRMED' : 'CAPACITY_REACHED';
                if (! $shuttleOk) {
                    $isAvailable = false;
                }
            } else {
                $shuttleStatus = 'SERVICE_UNAVAILABLE';
            }
        }

        return [
            'package_id' => $package->id,
            'is_fully_available' => $isAvailable,
            'hotel_status' => $hotelStatus,
            'shuttle_status' => $shuttleStatus,
        ];
    }
}
