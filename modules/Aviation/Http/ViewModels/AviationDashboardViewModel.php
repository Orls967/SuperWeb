<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\ViewModels;

use Modules\Aviation\Domain\Models\AviationAircraft;
use Modules\Aviation\Domain\Models\AviationCargoConsignment;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationSlot;
use Modules\Aviation\Domain\Models\AviationTicket;

class AviationDashboardViewModel
{
    /**
     * Mengumpulkan statistik ringkasan operasional harian aviasi.
     *
     * @return array{
     *     active_flights_count: int,
     *     departed_today_count: int,
     *     grounded_aircraft_count: int,
     *     active_aircraft_count: int,
     *     total_cargo_kg: float,
     *     total_passenger_booked: int,
     *     slot_utilization_avg_bps: int
     * }
     */
    public function toArray(): array
    {
        $today = now()->toDateString();

        $activeFlights = AviationFlight::whereIn('status', ['planned', 'boarding', 'airworthy_cleared'])->count();
        $departedToday = AviationFlight::where('status', 'departed')
            ->whereDate('departure_time', $today)
            ->count();

        $groundedAircraft = AviationAircraft::where('status', 'grounded')->count();
        $activeAircraft = AviationAircraft::where('status', 'active')->count();

        $cargoWeightG = AviationCargoConsignment::whereIn('status', ['accepted', 'dg_cleared', 'loaded', 'in_flight'])
            ->sum('weight_g');

        $paxBooked = AviationTicket::whereIn('status', ['issued', 'checked_in', 'boarded'])
            ->count();

        $avgSlotBps = (int) AviationSlot::where('status', 'confirmed')->avg('usage_rate_bps');

        return [
            'active_flights_count' => $activeFlights,
            'departed_today_count' => $departedToday,
            'grounded_aircraft_count' => $groundedAircraft,
            'active_aircraft_count' => $activeAircraft,
            'total_cargo_kg' => round($cargoWeightG / 1000, 2),
            'total_passenger_booked' => $paxBooked,
            'slot_utilization_avg_bps' => $avgSlotBps,
        ];
    }
}
