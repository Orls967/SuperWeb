<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Algorithms\BestAvailableRateCalculator;
use Modules\Hotel\Domain\Models\HotelRatePlanVersion;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Modules\Hotel\Domain\StateMachines\RatePlanVersionStateMachine;

/**
 * Tindakan Kalkulasi & Publikasi Massal Tarif Harian Seluruh Tipe Kamar.
 */
final class PublishDynamicRateCatalogAction
{
    /**
     * @return array{published_types: int, generated_versions: list<int>}
     */
    public function execute(string|int $propertyId, string $targetDate): array
    {
        $roomTypes = HotelRoomType::where('property_id', (string) $propertyId)->get();
        $generatedIds = [];

        foreach ($roomTypes as $type) {
            $baseRate = (int) ($type->base_rate_idr ?: 1200000);
            $bar = BestAvailableRateCalculator::calculate(
                baseRateIdr: $baseRate,
                occupancyRate: 0.80,
                leadDays: 7,
                isEventActive: false
            );

            $version = HotelRatePlanVersion::create([
                'rate_plan_id' => (string) $type->id,
                'version_number' => 1,
                'rate_idr' => (int) $bar['rate_idr'],
                'status' => RatePlanVersionStateMachine::STATUS_ACTIVE,
                'effective_date' => $targetDate,
            ]);

            $generatedIds[] = (int) $version->id;
        }

        return [
            'published_types' => count($roomTypes),
            'generated_versions' => $generatedIds,
        ];
    }
}
