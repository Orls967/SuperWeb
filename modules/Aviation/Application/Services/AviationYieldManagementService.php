<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Actions\OptimizeFareBucketsAction;
use Modules\Aviation\Domain\Algorithms\FareBucketYieldOptimizer;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationYieldBucket;

class AviationYieldManagementService
{
    public function __construct(
        protected OptimizeFareBucketsAction $optimizeAction,
        protected FareBucketYieldOptimizer $optimizer
    ) {}

    /**
     * Hitung ulang dan simpan alokasi seluruh ember tarif penerbangan.
     */
    public function rebalanceFlightBuckets(int $flightId): array
    {
        return $this->optimizeAction->execute($flightId);
    }

    /**
     * Ambil rincian inventaris tarif saat ini untuk suatu penerbangan.
     */
    public function getFlightYieldOverview(int $flightId): array
    {
        $flight = AviationFlight::with('yieldBuckets')->findOrFail($flightId);

        $buckets = $flight->yieldBuckets->sortByDesc('base_price_minor_unit')->values();

        return [
            'flight_id' => $flight->id,
            'flight_number' => $flight->flight_number,
            'capacity_seats' => $flight->capacity_seats,
            'status' => $flight->status,
            'buckets' => $buckets->map(fn (AviationYieldBucket $b) => [
                'bucket_code' => $b->bucket_code,
                'cabin_class' => $b->cabin_class,
                'fare_minor_unit' => $b->base_price_minor_unit,
                'allocated_capacity' => $b->allocated_capacity,
                'sold_count' => $b->sold_count,
                'protection_level' => $b->protection_level,
                'booking_limit' => $b->booking_limit,
                'available_seats' => $b->availableSeats(),
                'is_closed' => $b->is_closed,
            ])->all(),
        ];
    }

    /**
     * Catat penjualan tiket pada bucket tertentu dengan memotong kuota booking limit.
     */
    public function recordSeatSale(int $flightId, string $bucketCode, int $seats = 1): bool
    {
        return DB::transaction(function () use ($flightId, $bucketCode, $seats) {
            $bucket = AviationYieldBucket::where('flight_id', $flightId)
                ->where('bucket_code', $bucketCode)
                ->lockForUpdate()
                ->firstOrFail();

            if ($bucket->is_closed || $bucket->availableSeats() < $seats) {
                throw new DomainException(sprintf('Bucket [%s] pada penerbangan [%d] tidak memiliki kuota kursi yang cukup.', $bucketCode, $flightId));
            }

            $bucket->sold_count += $seats;
            if ($bucket->sold_count >= $bucket->allocated_capacity) {
                $bucket->is_closed = true;
            }
            $bucket->save();

            return true;
        });
    }

    /**
     * Jalankan evaluasi rebalance massal untuk seluruh penerbangan terjadwal yang akan berangkat dalam N hari.
     */
    public function batchRebalanceUpcomingFlights(int $daysAhead = 7): int
    {
        $flights = AviationFlight::where('status', 'scheduled')
            ->where('scheduled_departure', '>=', now())
            ->where('scheduled_departure', '<=', now()->addDays($daysAhead))
            ->get();

        $processed = 0;
        foreach ($flights as $flight) {
            try {
                $this->optimizeAction->execute($flight->id);
                $processed++;
            } catch (\Throwable $e) {
                // Lanjutkan penerbangan berikutnya
            }
        }

        return $processed;
    }
}
