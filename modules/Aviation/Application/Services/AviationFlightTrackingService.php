<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Carbon\Carbon;
use Modules\Aviation\Domain\Models\AviationFlight;

class AviationFlightTrackingService
{
    /**
     * Hitung progres jelajah penerbangan, sisa jarak, dan perkiraan waktu tiba aktual (ETA).
     *
     * @param int $flightId
     * @return array{
     *     flight_status: string,
     *     progress_percentage: int,
     *     elapsed_minutes: int,
     *     remaining_minutes: int,
     *     estimated_arrival_utc: string,
     *     delay_minutes: int,
     *     is_on_time: bool
     * }
     */
    public function trackFlight(int $flightId): array
    {
        $flight = AviationFlight::with(['route', 'originAirport', 'destinationAirport'])->findOrFail($flightId);
        $now = now();

        $scheduledDep = Carbon::parse($flight->departure_time);
        $scheduledArr = Carbon::parse($flight->arrival_time);
        $scheduledDurationMinutes = max(1, (int) $scheduledDep->diffInMinutes($scheduledArr));

        if ($flight->status === 'planned' || $flight->status === 'boarding') {
            return [
                'flight_status' => $flight->status,
                'progress_percentage' => 0,
                'elapsed_minutes' => 0,
                'remaining_minutes' => $scheduledDurationMinutes,
                'estimated_arrival_utc' => $scheduledArr->toIso8601String(),
                'delay_minutes' => max(0, (int) $scheduledDep->diffInMinutes($now, false)),
                'is_on_time' => $now->lte($scheduledDep),
            ];
        }

        if ($flight->status === 'arrived' || $flight->status === 'closed') {
            return [
                'flight_status' => $flight->status,
                'progress_percentage' => 100,
                'elapsed_minutes' => $scheduledDurationMinutes,
                'remaining_minutes' => 0,
                'estimated_arrival_utc' => $flight->actual_arrival ? Carbon::parse($flight->actual_arrival)->toIso8601String() : $scheduledArr->toIso8601String(),
                'delay_minutes' => 0,
                'is_on_time' => true,
            ];
        }

        // Sedang terbang (in-flight / departed)
        $actualDep = $flight->actual_departure ? Carbon::parse($flight->actual_departure) : $scheduledDep;
        $elapsedMinutes = max(0, (int) $actualDep->diffInMinutes($now));
        $progressBps = min(10000, (int) round(($elapsedMinutes / $scheduledDurationMinutes) * 10000));
        $progressPercentage = (int) round($progressBps / 100);

        $remainingMinutes = max(0, $scheduledDurationMinutes - $elapsedMinutes);
        $estimatedArrival = $actualDep->copy()->addMinutes($scheduledDurationMinutes);
        $delayMinutes = max(0, (int) $scheduledArr->diffInMinutes($estimatedArrival, false));

        return [
            'flight_status' => $flight->status,
            'progress_percentage' => $progressPercentage,
            'elapsed_minutes' => $elapsedMinutes,
            'remaining_minutes' => $remainingMinutes,
            'estimated_arrival_utc' => $estimatedArrival->toIso8601String(),
            'delay_minutes' => $delayMinutes,
            'is_on_time' => $delayMinutes <= 15, // Standar ketepatan waktu OTP (On-Time Performance <= 15 menit)
        ];
    }
}
