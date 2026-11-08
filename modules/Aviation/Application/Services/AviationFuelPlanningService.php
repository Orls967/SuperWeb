<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use InvalidArgumentException;
use Modules\Aviation\Domain\Models\AviationFlight;

class AviationFuelPlanningService
{
    /**
     * Hitung perencanaan bahan bakar penerbangan sesuai regulasi keselamatan ICAO Annex 6 & CASR Part 121.
     *
     * @return array{
     *     taxi_fuel_kg: int,
     *     trip_fuel_kg: int,
     *     contingency_fuel_kg: int,
     *     alternate_fuel_kg: int,
     *     final_reserve_fuel_kg: int,
     *     minimum_takeoff_fuel_kg: int,
     *     total_ramp_fuel_kg: int,
     *     endurance_minutes: int
     * }
     */
    public function planFuel(int $flightId, int $alternateDistanceKm = 150): array
    {
        $flight = AviationFlight::with(['aircraft', 'route'])->findOrFail($flightId);
        $aircraft = $flight->aircraft;
        $route = $flight->route;

        if (! $aircraft || ! $route) {
            throw new InvalidArgumentException("Penerbangan {$flight->flight_number} harus memiliki pesawat dan rute yang valid untuk perhitungan avtur.");
        }

        // Konsumsi rata-rata kg avtur per jam (misal: B737-800 = ~2.400 kg/jam = 40 kg/menit)
        $burnRateKgPerMinute = match ($aircraft->icao_type) {
            'B738', 'A320' => 40,
            'ATR72' => 12,
            'A333', 'B77W' => 105,
            default => 35,
        };

        $flightDurationMinutes = (int) ($route->scheduled_duration_minutes ?? 120);

        // 1. Taxi Fuel (Ground operation APU & taxi-out ke runway)
        $taxiFuelKg = match ($aircraft->icao_type) {
            'A333', 'B77W' => 450,
            'ATR72' => 80,
            default => 200,
        };

        // 2. Trip Fuel (Bahan bakar lepas landas, jelajah rute, dan mendarat)
        $tripFuelKg = $flightDurationMinutes * $burnRateKgPerMinute;

        // 3. Contingency Fuel (5% dari trip fuel atau minimum 5 menit holding)
        $contingencyFuelKg = max((int) round($tripFuelKg * 0.05), $burnRateKgPerMinute * 5);

        // 4. Alternate Airport Fuel (Bahan bakar terbang ke bandara pengalihan bila tujuan tertutup)
        $alternateSpeedKmH = 750;
        $alternateMinutes = (int) ceil(($alternateDistanceKm / $alternateSpeedKmH) * 60) + 15; // +15 mnt approach
        $alternateFuelKg = $alternateMinutes * $burnRateKgPerMinute;

        // 5. Final Reserve Fuel (Wajib 45 menit terbang holding pada 1.500 ft di atas bandara alternatif)
        $finalReserveMinutes = 45;
        $finalReserveFuelKg = $finalReserveMinutes * (int) round($burnRateKgPerMinute * 0.85); // 85% holding burn rate

        // Total Bahan Bakar
        $minimumTakeoffFuelKg = $tripFuelKg + $contingencyFuelKg + $alternateFuelKg + $finalReserveFuelKg;
        $totalRampFuelKg = $minimumTakeoffFuelKg + $taxiFuelKg;

        $totalBurnRate = max(1, $burnRateKgPerMinute);
        $totalEnduranceMinutes = (int) floor($minimumTakeoffFuelKg / $totalBurnRate);

        return [
            'taxi_fuel_kg' => $taxiFuelKg,
            'trip_fuel_kg' => $tripFuelKg,
            'contingency_fuel_kg' => $contingencyFuelKg,
            'alternate_fuel_kg' => $alternateFuelKg,
            'final_reserve_fuel_kg' => $finalReserveFuelKg,
            'minimum_takeoff_fuel_kg' => $minimumTakeoffFuelKg,
            'total_ramp_fuel_kg' => $totalRampFuelKg,
            'endurance_minutes' => $totalEnduranceMinutes,
        ];
    }
}
