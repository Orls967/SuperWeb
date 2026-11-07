<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\VenueExternalFactor;

final class WeatherAdaptiveVenueGovernorService
{
    /**
     * Ingest weather telemetry and calculate zone derating and hazard advisories.
     *
     * @return array{
     *     venue_id: int,
     *     rain_probability_bps: int,
     *     wind_speed_kmh: int,
     *     lightning_detected: bool,
     *     outdoor_zones_derated: int,
     *     indoor_capacity_surplus: int,
     *     safety_status: string,
     *     advisory: string
     * }
     */
    public function evaluateVenueWeatherRisk(int $venueId, int $rainProbBps, int $windKmh, bool $lightning): array
    {
        VenueExternalFactor::updateOrCreate(
            ['factor_type' => 'WEATHER_FORECAST'],
            [
                'factor_value' => (string) $rainProbBps,
                'weight_bps' => $rainProbBps,
                'is_active' => true,
                'metadata' => [
                    'venue_id' => $venueId,
                    'wind_speed' => $windKmh,
                    'lightning' => $lightning,
                ],
            ]
        );

        $outdoorZones = EntertainmentZone::where('venue_id', $venueId)
            ->where('zone_type', 'OUTDOOR')
            ->get();

        $indoorZones = EntertainmentZone::where('venue_id', $venueId)
            ->where('zone_type', 'INDOOR')
            ->get();

        $indoorCapacityTotal = (int) $indoorZones->sum('capacity_limit');

        $status = 'NORMAL_OPERATIONS';
        $advisory = 'Kondisi cuaca mendukung kegiatan luar ruangan.';
        $deratedCount = 0;

        if ($lightning || $windKmh > 55 || $rainProbBps >= 8000) {
            $status = 'EXTREME_WEATHER_LOCKDOWN';
            $advisory = 'BAHAYA PETIR/BADAI: Evakuasi seluruh zona terbuka ke aula dalam ruangan.';
            $deratedCount = $outdoorZones->count();

            foreach ($outdoorZones as $oz) {
                $oz->status = 'WEATHER_SUSPENDED';
                $oz->save();
            }
        } elseif ($rainProbBps >= 5000 || $windKmh > 35) {
            $status = 'ADVISORY_DERATING';
            $advisory = 'Peluang hujan tinggi: Kurangi kapasitas zona terbuka sebesar 30%.';
            $deratedCount = $outdoorZones->count();

            foreach ($outdoorZones as $oz) {
                $oz->status = 'DERATED';
                $oz->save();
            }
        }

        return [
            'venue_id' => $venueId,
            'rain_probability_bps' => $rainProbBps,
            'wind_speed_kmh' => $windKmh,
            'lightning_detected' => $lightning,
            'outdoor_zones_derated' => $deratedCount,
            'indoor_capacity_surplus' => $indoorCapacityTotal,
            'safety_status' => $status,
            'advisory' => $advisory,
        ];
    }
}
