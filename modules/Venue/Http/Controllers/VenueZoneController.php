<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Venue\Application\Actions\AdjustZoneCapacityForWeatherAction;
use Modules\Venue\Domain\Algorithms\ZoneCapacityGovernor;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;

class VenueZoneController extends Controller
{
    public function index(Request $request): View
    {
        $venues = EntertainmentVenue::with('zones')->get();

        $zones = EntertainmentZone::with('venue')
            ->orderBy('id')
            ->get()
            ->map(function (EntertainmentZone $zone) {
                $legalCap = (int) $zone->capacity_limit;
                $effectiveCap = ZoneCapacityGovernor::calculateEffectiveCapacity($legalCap, 500);
                $occupancyRate = $effectiveCap > 0
                    ? round(($zone->current_occupancy / $effectiveCap) * 100, 1)
                    : 0;

                $riskLevel = match (true) {
                    $occupancyRate >= 95.0 => 'CRITICAL',
                    $occupancyRate >= 85.0 => 'WARNING',
                    $occupancyRate >= 60.0 => 'ELEVATED',
                    default => 'NORMAL',
                };

                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'zone_code' => $zone->zone_code,
                    'venue_name' => $zone->venue?->name ?? 'Venue',
                    'capacity_limit' => $legalCap,
                    'effective_capacity' => $effectiveCap,
                    'current_occupancy' => (int) $zone->current_occupancy,
                    'occupancy_rate' => $occupancyRate,
                    'risk_level' => $riskLevel,
                    'crowd_lockdown_active' => (bool) $zone->crowd_lockdown_active,
                ];
            });

        return view('venue::zones.index', [
            'venues' => $venues,
            'zones' => $zones,
        ]);
    }

    public function crowdSafety(): View
    {
        return view('venue::zones.crowd-safety');
    }

    public function toggleLockdown(EntertainmentZone $zone): RedirectResponse
    {
        $zone->crowd_lockdown_active = ! $zone->crowd_lockdown_active;
        $zone->save();

        $status = $zone->crowd_lockdown_active ? 'DIAKTIFKAN' : 'DINONAKTIFKAN';

        return redirect()->route('venue.zones.index')
            ->with('success', "Lockdown zona {$zone->name} berhasil {$status}");
    }

    public function adjustForWeather(
        Request $request,
        EntertainmentZone $zone,
        AdjustZoneCapacityForWeatherAction $action
    ): RedirectResponse {
        $rainProbBps = (int) $request->input('rain_probability_bps', 6000);
        $windSpeedKmh = (int) $request->input('wind_speed_kmh', 45);

        $result = $action->execute(
            zone: $zone,
            rainProbabilityBps: $rainProbBps,
            windSpeedKmh: $windSpeedKmh
        );

        return redirect()->route('venue.zones.index')
            ->with('success', "Kapasitas zona disesuaikan: {$result['adjusted_capacity']} orang ({$result['reduction_reason']})");
    }
}

