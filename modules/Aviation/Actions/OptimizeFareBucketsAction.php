<?php

declare(strict_types=1);

namespace Modules\Aviation\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Algorithms\FareBucketYieldOptimizer;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationYieldBucket;

class OptimizeFareBucketsAction
{
    public function __construct(
        protected FareBucketYieldOptimizer $optimizer
    ) {}

    /**
     * Optimalkan alokasi kursi untuk semua fare bucket pada penerbangan tertentu.
     *
     * @param  array<int, array{code: string, fare: int, mean_demand: float|int, std_dev: float|int, cabin_class?: string}>  $fareClasses
     * @return array{flight_id: int, buckets_created_or_updated: int, certificate_hash: string}
     */
    public function execute(int $flightId, array $fareClasses = []): array
    {
        return DB::transaction(function () use ($flightId, $fareClasses) {
            $flight = AviationFlight::lockForUpdate()->findOrFail($flightId);

            if (in_array($flight->status, ['departed', 'arrived', 'cancelled'], true)) {
                throw new DomainException(sprintf('Penerbangan [%s] dengan status [%s] tidak dapat dioptimalkan hasilnya.', $flight->flight_number, $flight->status));
            }

            $capacity = (int) $flight->capacity_seats;

            // Jika daftar fare classes kosong, gunakan profil default industri
            if (empty($fareClasses)) {
                $baseFare = (int) $flight->base_price_minor_unit;
                $fareClasses = [
                    ['code' => 'Y', 'fare' => (int) round($baseFare * 1.5), 'mean_demand' => (int) round($capacity * 0.20), 'std_dev' => 6, 'cabin_class' => 'economy'],
                    ['code' => 'B', 'fare' => (int) round($baseFare * 1.25), 'mean_demand' => (int) round($capacity * 0.25), 'std_dev' => 8, 'cabin_class' => 'economy'],
                    ['code' => 'M', 'fare' => (int) round($baseFare * 1.0), 'mean_demand' => (int) round($capacity * 0.30), 'std_dev' => 10, 'cabin_class' => 'economy'],
                    ['code' => 'Q', 'fare' => (int) round($baseFare * 0.75), 'mean_demand' => (int) round($capacity * 0.35), 'std_dev' => 12, 'cabin_class' => 'economy'],
                    ['code' => 'V', 'fare' => (int) round($baseFare * 0.55), 'mean_demand' => (int) round($capacity * 0.40), 'std_dev' => 15, 'cabin_class' => 'economy'],
                ];
            }

            $optResult = $this->optimizer->calculateProtectionAndBookingLimits($capacity, $fareClasses);

            $count = 0;
            foreach ($optResult['buckets'] as $b) {
                $matchingClass = collect($fareClasses)->firstWhere('code', $b['code']);
                $cabin = $matchingClass['cabin_class'] ?? 'economy';

                AviationYieldBucket::updateOrCreate(
                    [
                        'flight_id' => $flight->id,
                        'bucket_code' => $b['code'],
                    ],
                    [
                        'cabin_class' => $cabin,
                        'base_price_minor_unit' => $b['fare'],
                        'allocated_capacity' => $b['booking_limit'],
                        'protection_level' => $b['protection_level'],
                        'booking_limit' => $b['booking_limit'],
                        'is_closed' => $b['booking_limit'] <= 0,
                        'algorithm_certificate_hash' => $optResult['certificate_hash'],
                    ]
                );
                $count++;
            }

            return [
                'flight_id' => $flight->id,
                'buckets_created_or_updated' => $count,
                'certificate_hash' => $optResult['certificate_hash'],
            ];
        });
    }
}
