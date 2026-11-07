<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Hotel\Domain\Algorithms\BestAvailableRateCalculator;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRatePlanVersion;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Modules\Hotel\Domain\Services\HotelExternalFactors;
use Modules\Hotel\Domain\StateMachines\RatePlanVersionStateMachine;

/**
 * Publikasi Versi Rate Plan & Tarif Dinamis Harian H2 (D91-4)
 */
final class PublishRatePlanAction
{
    /**
     * @param  string  $fromDate  (Y-m-d)
     * @param  string  $toDate  (Y-m-d)
     * @return array{published_records: int, version_code: string}
     */
    public function execute(
        int $propertyId,
        string $fromDate,
        string $toDate,
        ?int $approverUserId = null
    ): array {
        return DB::transaction(function () use ($propertyId, $fromDate, $toDate, $approverUserId): array {
            $property = HotelProperty::findOrFail($propertyId);
            $roomTypes = HotelRoomType::where('property_id', $propertyId)->get();

            $start = CarbonImmutable::parse($fromDate);
            $end = CarbonImmutable::parse($toDate);
            $days = (int) $start->diffInDays($end) + 1;

            $versionCode = 'RPV-'.date('Ymd').'-'.substr(bin2hex(random_bytes(3)), 0, 6);
            $count = 0;
            $city = $property->city ?? 'Bali';
            $isEvent = HotelExternalFactors::isCityEventActive($city);

            for ($i = 0; $i < $days; $i++) {
                $bDate = $start->addDays($i)->format('Y-m-d');
                $leadDays = max(0, (int) CarbonImmutable::now()->diffInDays($start->addDays($i)));

                foreach ($roomTypes as $rt) {
                    $bar = BestAvailableRateCalculator::calculate(
                        baseRateIdr: (int) $rt->base_price,
                        occupancyRate: 0.75, // okupansi rata-rata proyeksi
                        leadDays: $leadDays,
                        isEventActive: $isEvent,
                        floorRateIdr: 450000,
                        ceilingRateIdr: 3000000
                    );

                    HotelRatePlanVersion::create([
                        'property_id' => $propertyId,
                        'room_type_id' => $rt->id,
                        'version_code' => $versionCode,
                        'business_date' => $bDate,
                        'base_rate_idr' => (int) $rt->base_price,
                        'calculated_bar_rate_idr' => $bar['rate_idr'],
                        'floor_rate_idr' => 450000,
                        'ceiling_rate_idr' => 3000000,
                        'occupancy_bps' => $bar['applied_occupancy_bps'],
                        'lead_bps' => $bar['applied_lead_bps'],
                        'event_bps' => $bar['applied_event_bps'],
                        'is_event_boosted' => $isEvent,
                        'status' => RatePlanVersionStateMachine::STATUS_PUBLISHED,
                        'approved_by_user_id' => $approverUserId,
                        'published_at' => CarbonImmutable::now(),
                    ]);
                    $count++;
                }
            }

            return [
                'published_records' => $count,
                'version_code' => $versionCode,
            ];
        });
    }
}
