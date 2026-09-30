<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\Load;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;

class ConsolidateLclAction
{
    /**
     * Consolidate LCL shipments at CFS using First-Fit-Decreasing (FFD) by volume and weight.
     *
     * @param  array<int, Shipment>  $shipments
     * @param  array<int, Container>  $availableContainers
     * @return array<int, Load>
     */
    public function execute(
        Location $cfsLocation,
        Location $destinationLocation,
        array $shipments,
        array $availableContainers
    ): array {
        return DB::transaction(function () use (
            $cfsLocation,
            $destinationLocation,
            $shipments,
            $availableContainers
        ) {
            // 1. Flatten all packages / shipments into items list
            $items = [];
            foreach ($shipments as $shipment) {
                $packages = $shipment->packages;
                if ($packages->isEmpty()) {
                    // Fallback to single shipment item
                    $weightKg = max(1.0, ((float) $shipment->total_chargeable_weight_g) / 1000);
                    $volumeDm3 = 100;
                    $items[] = [
                        'shipment' => $shipment,
                        'package' => null,
                        'weightKg' => $weightKg,
                        'volumeDm3' => $volumeDm3,
                        'dgClass' => null,
                        'isReefer' => false,
                    ];
                } else {
                    foreach ($packages as $pkg) {
                        $isReefer = ($pkg->temp_min_c10 !== null || $pkg->temp_max_c10 !== null);
                        $volDm3 = max(1, (int) round(((float) ($pkg->length_mm * $pkg->width_mm * $pkg->height_mm)) / 1_000_000));
                        $items[] = [
                            'shipment' => $shipment,
                            'package' => $pkg,
                            'weightKg' => max(0.5, ((float) $pkg->weight_g) / 1000),
                            'volumeDm3' => $volDm3,
                            'dgClass' => $pkg->dg_un_number ? ($pkg->dg_class ?? '9') : null,
                            'isReefer' => $isReefer,
                        ];
                    }
                }
            }

            // 2. Sort items descending by volume (First-Fit-Decreasing)
            usort($items, fn ($a, $b) => $b['volumeDm3'] <=> $a['volumeDm3']);

            /** @var array<int, Load> $activeLoads */
            $activeLoads = [];
            $containerIndex = 0;

            foreach ($items as $item) {
                $placed = false;

                // Attempt to place in existing active loads
                foreach ($activeLoads as $load) {
                    if ($load->is_reefer !== $item['isReefer']) {
                        continue;
                    }

                    if (! $load->canAcceptDgClass($item['dgClass'])) {
                        continue;
                    }

                    if (! $load->canFit($item['weightKg'], $item['volumeDm3'])) {
                        continue;
                    }

                    $load->addItem(
                        shipment: $item['shipment'],
                        package: $item['package'],
                        weightKg: $item['weightKg'],
                        volumeDm3: $item['volumeDm3'],
                        dgClass: $item['dgClass'],
                        isReefer: $item['isReefer']
                    );

                    $placed = true;
                    break;
                }

                if (! $placed) {
                    // Open a new Container load
                    if ($containerIndex >= count($availableContainers)) {
                        throw new \RuntimeException('Tidak tersedia kontainer yang cukup di CFS untuk menampung seluruh kargo LCL.');
                    }

                    $container = $availableContainers[$containerIndex++];

                    // Assert container is not in another active load
                    Load::assertUnitNotActive(Container::class, $container->id);

                    $loadNumber = 'LOD-LCL-'.strtoupper(Str::random(8));
                    $isReefer = $item['isReefer'];
                    $maxWeight = (float) $container->payloadCapacityKg();
                    $maxVol = 33000; // ~33 CBM for 20ft container (33,000 dm3)

                    $newLoad = Load::create([
                        'load_number' => $loadNumber,
                        'load_type' => 'container',
                        'loadable_type' => Container::class,
                        'loadable_id' => $container->id,
                        'service_type' => 'lcl',
                        'origin_location_id' => $cfsLocation->id,
                        'destination_location_id' => $destinationLocation->id,
                        'status' => 'planning',
                        'max_weight_kg' => (string) $maxWeight,
                        'max_volume_dm3' => $maxVol,
                        'current_weight_kg' => '0.000',
                        'current_volume_dm3' => 0,
                        'is_reefer' => $isReefer,
                    ]);

                    $newLoad->addItem(
                        shipment: $item['shipment'],
                        package: $item['package'],
                        weightKg: $item['weightKg'],
                        volumeDm3: $item['volumeDm3'],
                        dgClass: $item['dgClass'],
                        isReefer: $item['isReefer']
                    );

                    $activeLoads[] = $newLoad;
                }
            }

            return $activeLoads;
        });
    }
}
