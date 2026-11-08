<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Modules\Aviation\Domain\Models\AviationFlight;

class AviationRouteProfitabilityService
{
    /**
     * Hitung metrik profitabilitas komprehensif (RASK, CASK, Laba Bersih, Break-Even Load Factor).
     *
     * @return array{
     *     flight_id: int,
     *     ask_seat_km: float,
     *     rpk_passenger_km: float,
     *     load_factor_percentage: float,
     *     total_revenue_idr: int,
     *     total_operating_cost_idr: int,
     *     net_profit_idr: int,
     *     rask_idr_per_seat_km: float,
     *     cask_idr_per_seat_km: float,
     *     break_even_load_factor_percentage: float,
     *     profit_margin_percentage: float
     * }
     */
    public function calculateFlightProfitability(int $flightId): array
    {
        $flight = AviationFlight::with(['departureAirport', 'arrivalAirport', 'tickets'])->findOrFail($flightId);

        $distanceKm = (float) max(50.0, ($flight->distance_miles ?? 500) * 1.852);
        $capacity = max(1, (int) $flight->capacity_seats);
        $ask = $capacity * $distanceKm;

        $soldTickets = $flight->tickets->whereIn('status', ['confirmed', 'checked_in', 'boarded']);
        $passengerCount = $soldTickets->count();
        $rpk = $passengerCount * $distanceKm;
        $loadFactor = ($passengerCount / (float) $capacity) * 100.0;

        $ticketRevenueMinor = (int) $soldTickets->sum('total_price_minor_unit');
        $ancillaryRevenueMinor = (int) ($passengerCount * 7500000); // Rata-rata bagasi & makanan berbayar Rp75.000
        $totalRevenueMinor = $ticketRevenueMinor + $ancillaryRevenueMinor;
        $totalRevenueIdr = (int) round($totalRevenueMinor / 100);

        // Biaya Operasional: Bahan bakar (40%), Biaya Bandara/ATC (20%), Kru (15%), Depresiasi Armada & Perawatan (25%)
        $fuelCostIdr = (int) round($distanceKm * 45000); // Asumsi konsumsi avtur rute domestik
        $airportCostIdr = (int) round(15000000 + ($passengerCount * 120000));
        $crewCostIdr = (int) round(12000000 + ($distanceKm * 8000));
        $maintenanceCostIdr = (int) round(18000000 + ($distanceKm * 12000));

        $totalCostIdr = $fuelCostIdr + $airportCostIdr + $crewCostIdr + $maintenanceCostIdr;
        $netProfitIdr = $totalRevenueIdr - $totalCostIdr;

        $rask = $ask > 0 ? ($totalRevenueIdr / $ask) : 0.0;
        $cask = $ask > 0 ? ($totalCostIdr / $ask) : 0.0;
        $yieldPerRpk = $rpk > 0 ? ($totalRevenueIdr / $rpk) : 0.0;

        $breakEvenLoadFactor = $yieldPerRpk > 0 ? min(100.0, ($cask / $yieldPerRpk) * 100.0) : 100.0;
        $profitMargin = $totalRevenueIdr > 0 ? (($netProfitIdr / (float) $totalRevenueIdr) * 100.0) : 0.0;

        return [
            'flight_id' => $flight->id,
            'ask_seat_km' => $ask,
            'rpk_passenger_km' => $rpk,
            'load_factor_percentage' => round($loadFactor, 2),
            'total_revenue_idr' => $totalRevenueIdr,
            'total_operating_cost_idr' => $totalCostIdr,
            'net_profit_idr' => $netProfitIdr,
            'rask_idr_per_seat_km' => round($rask, 4),
            'cask_idr_per_seat_km' => round($cask, 4),
            'break_even_load_factor_percentage' => round($breakEvenLoadFactor, 2),
            'profit_margin_percentage' => round($profitMargin, 2),
        ];
    }

    /**
     * Analisis agregat seluruh penerbangan pada pasangan rute tertentu.
     */
    public function analyzeRouteAggregate(int $airlineId, int $departureAirportId, int $arrivalAirportId): array
    {
        $flights = AviationFlight::where('airline_id', $airlineId)
            ->where('departure_airport_id', $departureAirportId)
            ->where('arrival_airport_id', $arrivalAirportId)
            ->whereIn('status', ['arrived', 'departed', 'scheduled'])
            ->get();

        if ($flights->isEmpty()) {
            return [
                'flight_count' => 0,
                'avg_load_factor' => 0.0,
                'total_net_profit_idr' => 0,
                'is_profitable' => false,
            ];
        }

        $totalProfit = 0;
        $totalLf = 0.0;
        foreach ($flights as $f) {
            $calc = $this->calculateFlightProfitability($f->id);
            $totalProfit += $calc['net_profit_idr'];
            $totalLf += $calc['load_factor_percentage'];
        }

        $avgLf = $totalLf / $flights->count();

        return [
            'flight_count' => $flights->count(),
            'avg_load_factor' => round($avgLf, 2),
            'total_net_profit_idr' => $totalProfit,
            'is_profitable' => $totalProfit > 0,
        ];
    }
}
