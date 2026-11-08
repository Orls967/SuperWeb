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

        $totalOperatingCostIdr = $fuelCostIdr + $airportCostIdr + $crewCostIdr + $maintenanceCostIdr;
        $netProfitIdr = $totalRevenueIdr - $totalOperatingCostIdr;

        $rask = $ask > 0 ? ($totalRevenueIdr / $ask) : 0.0;
        $cask = $ask > 0 ? ($totalOperatingCostIdr / $ask) : 0.0;

        $yieldPerRpk = $rpk > 0 ? ($totalRevenueIdr / $rpk) : 0.0;
        $breakEvenLf = ($yieldPerRpk > 0 && $ask > 0) ? min(100.0, ($totalOperatingCostIdr / ($yieldPerRpk * $ask)) * 100.0) : 100.0;

        $profitMargin = $totalRevenueIdr > 0 ? ($netProfitIdr / (float) $totalRevenueIdr) * 100.0 : 0.0;

        return [
            'flight_id' => $flight->id,
            'ask_seat_km' => round($ask, 2),
            'rpk_passenger_km' => round($rpk, 2),
            'load_factor_percentage' => round($loadFactor, 2),
            'total_revenue_idr' => $totalRevenueIdr,
            'total_operating_cost_idr' => $totalOperatingCostIdr,
            'net_profit_idr' => $netProfitIdr,
            'rask_idr_per_seat_km' => round($rask, 4),
            'cask_idr_per_seat_km' => round($cask, 4),
            'break_even_load_factor_percentage' => round($breakEvenLf, 2),
            'profit_margin_percentage' => round($profitMargin, 2),
        ];
    }

    /**
     * Analisis efisiensi hub jaringan penerbangan (Hub Connectivity Index).
     *
     * @param  array<int, array{origin: string, destination: string, connecting_pax: int, transfer_time_minutes: int}>  $connections
     * @return array{
     *     total_connecting_passengers: int,
     *     average_transfer_time_minutes: float,
     *     missed_connection_risk_percentage: float,
     *     hub_connectivity_rating: string
     * }
     */
    public function analyzeHubConnectivity(array $connections): array
    {
        $totalPax = 0;
        $totalTime = 0;
        $tightConnections = 0;

        foreach ($connections as $c) {
            $pax = (int) $c['connecting_pax'];
            $time = (int) $c['transfer_time_minutes'];
            $totalPax += $pax;
            $totalTime += ($time * $pax);

            // Minimum Connecting Time (MCT) domestik ke internasional di bawah 60 menit dianggap kritis
            if ($time < 60) {
                $tightConnections += $pax;
            }
        }

        $avgTime = $totalPax > 0 ? ($totalTime / (float) $totalPax) : 0.0;
        $missedRisk = $totalPax > 0 ? ($tightConnections / (float) $totalPax) * 100.0 : 0.0;

        $rating = 'EXCELLENT';
        if ($missedRisk > 25.0) {
            $rating = 'HIGH_MCT_RISK';
        } elseif ($missedRisk > 10.0) {
            $rating = 'MODERATE';
        }

        return [
            'total_connecting_passengers' => $totalPax,
            'average_transfer_time_minutes' => round($avgTime, 1),
            'missed_connection_risk_percentage' => round($missedRisk, 2),
            'hub_connectivity_rating' => $rating,
        ];
    }

    /**
     * Evaluasi pemilihan tipe pesawat untuk rute spesifik (Fleet Gauge Optimization).
     *
     * @return array{
     *     recommended_aircraft_type: string,
     *     estimated_spill_passengers: int,
     *     estimated_spoilage_seats: int,
     *     expected_trip_profit_idr: int
     * }
     */
    public function evaluateFleetGaugeAssignment(
        int $expectedDemand,
        int $distanceKm,
        int $narrowbodyCapacity = 180,
        int $widebodyCapacity = 300
    ): array {
        $fareIdr = max(500000, $distanceKm * 1200);

        // Opsi 1: Narrowbody
        $nbPassengers = min($expectedDemand, $narrowbodyCapacity);
        $nbSpill = max(0, $expectedDemand - $narrowbodyCapacity);
        $nbSpoilage = max(0, $narrowbodyCapacity - $expectedDemand);
        $nbCost = 75000000 + ($distanceKm * 40000);
        $nbProfit = ($nbPassengers * $fareIdr) - $nbCost;

        // Opsi 2: Widebody
        $wbPassengers = min($expectedDemand, $widebodyCapacity);
        $wbSpill = max(0, $expectedDemand - $widebodyCapacity);
        $wbSpoilage = max(0, $widebodyCapacity - $expectedDemand);
        $wbCost = 140000000 + ($distanceKm * 75000);
        $wbProfit = ($wbPassengers * $fareIdr) - $wbCost;

        if ($wbProfit > $nbProfit) {
            return [
                'recommended_aircraft_type' => 'WIDEBODY_A330',
                'estimated_spill_passengers' => $wbSpill,
                'estimated_spoilage_seats' => $wbSpoilage,
                'expected_trip_profit_idr' => $wbProfit,
            ];
        }

        return [
            'recommended_aircraft_type' => 'NARROWBODY_A320',
            'estimated_spill_passengers' => $nbSpill,
            'estimated_spoilage_seats' => $nbSpoilage,
            'expected_trip_profit_idr' => $nbProfit,
        ];
    }
}
