<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundleInput;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundlePricer;
use Modules\Venue\Domain\Models\DynamicBundleComponent;
use Modules\Venue\Domain\Models\DynamicBundlePackage;

class AllocateDynamicBundleAction
{
    public function __construct(
        protected DynamicBundlePricer $pricer
    ) {}

    /**
     * @param  array<int, array{name: string, floor_cost: int, base_price: int, component_type?: string, metadata?: array}>  $components
     */
    public function execute(
        int $eventId,
        string $packageName,
        array $components,
        int $occupancyPercentage = 80,
        int $leadTimeDays = 7
    ): DynamicBundlePackage {
        return DB::transaction(function () use ($eventId, $packageName, $components, $occupancyPercentage, $leadTimeDays) {
            $input = new DynamicBundleInput(
                components: $components,
                venueOccupancyPercentage: $occupancyPercentage,
                hotelOccupancyPercentage: $occupancyPercentage,
                leadTimeDays: $leadTimeDays
            );

            $pricing = $this->pricer->calculatePrice($input);

            $packageCode = 'BDL-'.strtoupper(substr(hash('sha256', $eventId.'-'.$packageName.'-'.uniqid('', true)), 0, 12));

            /** @var DynamicBundlePackage $package */
            $package = DynamicBundlePackage::query()->create([
                'package_code' => $packageCode,
                'event_id' => $eventId,
                'package_name' => $packageName,
                'base_price_idr' => $pricing->totalBasePriceIdr,
                'floor_cost_idr' => $pricing->totalFloorCostIdr,
                'final_price_idr' => $pricing->finalBundlePriceIdr,
                'surge_bps' => $pricing->surgeBps,
                'lead_time_discount_bps' => $pricing->leadTimeDiscountBps,
                'status' => 'PUBLISHED',
            ]);

            foreach ($components as $comp) {
                DynamicBundleComponent::query()->create([
                    'bundle_package_id' => $package->id,
                    'component_type' => $comp['component_type'] ?? 'TICKET',
                    'name' => $comp['name'] ?? 'Bundle Item',
                    'base_price_idr' => (int) ($comp['base_price'] ?? 0),
                    'floor_cost_idr' => (int) ($comp['floor_cost'] ?? 0),
                    'metadata' => $comp['metadata'] ?? null,
                ]);
            }

            return $package->load('components');
        });
    }
}
