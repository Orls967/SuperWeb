<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Sagas;

use Illuminate\Support\Facades\DB;
use Modules\Hotel\Contracts\HotelPackageAvailabilityValidator;
use Modules\Logistics\Contracts\EventShuttleFleetScheduler;
use Modules\Venue\Application\Actions\AllocateDynamicBundleAction;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundleInput;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundlePricer;
use Modules\Venue\Domain\Models\DynamicBundlePackage;
use Modules\Venue\Domain\StateMachines\DynamicBundlePackageStateMachine;

final class DynamicBundleAllocationSaga
{
    public function __construct(
        private readonly AllocateDynamicBundleAction $allocateAction,
        private readonly DynamicBundlePricer $bundlePricer,
        private readonly ?HotelPackageAvailabilityValidator $hotelValidator = null,
        private readonly ?EventShuttleFleetScheduler $shuttleScheduler = null
    ) {}

    /**
     * @return array{saga_id: string, status: string, package_id: int, buyer_user_id: int, bundle_price_idr: int, total_savings_idr: int}
     */
    public function execute(
        int $packageId,
        int $buyerUserId,
        ?int $hotelPackageId = null,
        ?int $shuttleRouteId = null
    ): array {
        $sagaId = 'SAGA-BND-'.bin2hex(random_bytes(8));

        return DB::transaction(function () use ($packageId, $buyerUserId, $hotelPackageId, $shuttleRouteId, $sagaId): array {
            $package = DynamicBundlePackage::query()->lockForUpdate()->findOrFail($packageId);

            if ($package->status !== DynamicBundlePackageStateMachine::STATUS_PUBLISHED) {
                throw new \DomainException("Paket bundle belum aktif / berstatus {$package->status}");
            }

            // Step 1: Validasi kamar hotel lintas modul jika disertakan
            if ($hotelPackageId !== null && $this->hotelValidator !== null) {
                $checkIn = new \DateTimeImmutable('tomorrow');
                $checkOut = $checkIn->modify('+2 days');
                $isAvailable = $this->hotelValidator->validateAvailability($hotelPackageId, $checkIn, $checkOut, 1);
                if (! $isAvailable) {
                    throw new \DomainException("Alokasi paket bundle dibatalkan: kamar hotel pilihan #{$hotelPackageId} tidak tersedia.");
                }
            }

            // Step 2: Validasi armada shuttle bus lintas modul jika disertakan
            if ($shuttleRouteId !== null && $this->shuttleScheduler !== null) {
                $departure = new \DateTimeImmutable('tomorrow 16:00:00');
                $shuttleScheduled = $this->shuttleScheduler->scheduleEventShuttle(
                    $package->event_id,
                    $shuttleRouteId,
                    1,
                    $departure
                );
                if (! $shuttleScheduled) {
                    throw new \DomainException("Alokasi paket bundle dibatalkan: rute shuttle #{$shuttleRouteId} penuh atau tidak beroperasi.");
                }
            }

            // Step 3: Verifikasi dynamic bundle pricer & floor price
            $bundleInput = new DynamicBundleInput(
                $package->total_base_price_idr,
                $package->discount_bps,
                $package->floor_price_idr
            );
            $pricingResult = $this->bundlePricer->calculate($bundleInput);

            // Step 4: Alokasi bundle ke buyer
            $allocatedPackage = $this->allocateAction->execute(
                $package,
                $buyerUserId,
                $pricingResult->bundlePriceIdr
            );

            return [
                'saga_id' => $sagaId,
                'status' => 'ALLOCATED',
                'package_id' => $allocatedPackage->id,
                'buyer_user_id' => $buyerUserId,
                'bundle_price_idr' => $pricingResult->bundlePriceIdr,
                'total_savings_idr' => $pricingResult->totalSavingsIdr,
            ];
        });
    }
}
