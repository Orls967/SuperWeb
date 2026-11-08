<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Carbon\Carbon;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationSeatInventory;

class AviationPricingEngineService
{
    /**
     * Hitung tarif dinamis kursi berdasarkan kurva okupansi kabin dan jendela hari keberangkatan (D-Day).
     *
     * @param int $flightId
     * @param string $cabin 'Y' (Ekonomi) atau 'J' (Bisnis)
     * @param string $fareBasis 'PROMO', 'STD', atau 'FLEX'
     * @return array{
     *     base_fare_minor: int,
     *     tax_minor: int,
     *     total_fare_minor: int,
     *     multiplier_bps: int,
     *     occupancy_rate_bps: int,
     *     days_to_departure: int
     * }
     */
    public function calculateDynamicFare(int $flightId, string $cabin = 'Y', string $fareBasis = 'STD'): array
    {
        $flight = AviationFlight::with(['route'])->findOrFail($flightId);
        $baseRouteFareMinor = (int) ($flight->route->base_fare_minor ?? 100000000); // Bawaan Rp 1.000.000

        $inventory = AviationSeatInventory::where('flight_id', $flight->id)
            ->where('cabin', $cabin)
            ->first();

        $totalCapacity = $inventory ? max(1, $inventory->total_capacity) : 150;
        $bookedSeats = $inventory ? ($inventory->held_seats + $inventory->booked_seats) : 0;
        $occupancyRateBps = min(10000, (int) round(($bookedSeats / $totalCapacity) * 10000));

        $daysToDeparture = max(0, (int) now()->diffInDays(Carbon::parse($flight->departure_time), false));

        // 1. Pengali Okupansi (Tingkat keterisian tinggi menaikkan harga)
        $occupancyMultiplierBps = match (true) {
            $occupancyRateBps >= 9000 => 14000, // +40%
            $occupancyRateBps >= 7500 => 12500, // +25%
            $occupancyRateBps >= 5000 => 11000, // +10%
            default => 10000,                  // 100% basis
        };

        // 2. Pengali Waktu Pemesanan (Pemesanan mepet keberangkatan lebih mahal)
        $urgencyMultiplierBps = match (true) {
            $daysToDeparture <= 1 => 13500,  // H-1: +35%
            $daysToDeparture <= 3 => 12000,  // H-3: +20%
            $daysToDeparture <= 7 => 11000,  // H-7: +10%
            $daysToDeparture >= 30 => 8500,  // Early bird: -15%
            default => 10000,
        };

        // 3. Pengali Kelas Tarif & Kabin
        $classMultiplierBps = match ($fareBasis) {
            'PROMO' => 8000,   // -20%
            'FLEX' => 12500,   // +25%
            default => 10000,
        };

        $cabinMultiplierBps = match ($cabin) {
            'J' => 25000, // Bisnis = 2.5x tarif dasar
            default => 10000,
        };

        // Total Pengali Terpadu (Normalized)
        $combinedBps = (int) round(
            ($occupancyMultiplierBps / 10000) *
            ($urgencyMultiplierBps / 10000) *
            ($classMultiplierBps / 10000) *
            ($cabinMultiplierBps / 10000) * 10000
        );

        $adjustedBaseFare = (int) round(($baseRouteFareMinor * $combinedBps) / 10000);
        $taxMinor = (int) round($adjustedBaseFare * 0.11); // PPN 11%
        $totalFareMinor = $adjustedBaseFare + $taxMinor;

        return [
            'base_fare_minor' => $adjustedBaseFare,
            'tax_minor' => $taxMinor,
            'total_fare_minor' => $totalFareMinor,
            'multiplier_bps' => $combinedBps,
            'occupancy_rate_bps' => $occupancyRateBps,
            'days_to_departure' => $daysToDeparture,
        ];
    }
}
