<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Aviation\Domain\Models\AviationAircraft;
use Modules\Aviation\Domain\Models\AviationAirport;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationOperator;
use Modules\Aviation\Domain\Models\AviationRoute;

final class AviationAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'avi:audit';
    }

    public function corrupt(): string
    {
        $operator = AviationOperator::firstOrCreate(
            ['code' => 'CORRUPT'],
            ['name' => 'Corrupted Airlines', 'country' => 'ID']
        );

        $airport1 = AviationAirport::firstOrCreate(
            ['iata_code' => 'CR1'],
            [
                'icao_code' => 'WCR1',
                'name' => 'Corrupt Airport 1',
                'city' => 'Kota 1',
                'province' => 'Provinsi 1',
                'island' => 'Jawa',
                'hourly_capacity' => 20,
            ]
        );

        $airport2 = AviationAirport::firstOrCreate(
            ['iata_code' => 'CR2'],
            [
                'icao_code' => 'WCR2',
                'name' => 'Corrupt Airport 2',
                'city' => 'Kota 2',
                'province' => 'Provinsi 2',
                'island' => 'Bali',
                'hourly_capacity' => 20,
            ]
        );

        $route = AviationRoute::firstOrCreate(
            ['flight_number' => 'GA-CORRUPT-999'],
            [
                'origin_airport_id' => $airport1->id,
                'destination_airport_id' => $airport2->id,
                'distance_km' => 500,
                'scheduled_duration_minutes' => 60,
                'base_fare_minor' => 50000000,
                'currency' => 'IDR',
            ]
        );

        $aircraft = AviationAircraft::firstOrCreate(
            ['registration_mark' => 'PK-CORRUPT'],
            [
                'icao_type' => 'B738',
                'model_name' => 'Boeing 737 Corrupt',
                'operator_id' => $operator->id,
                'cabin_y_capacity' => 150,
                'cabin_j_capacity' => 12,
                'mtow_kg' => 79000,
                'oew_kg' => 41400,
                'cg_forward_limit_mm' => 8600,
                'cg_aft_limit_mm' => 9600,
                'airworthiness_expires_at' => now()->subDays(30),
                'status' => 'grounded',
            ]
        );

        // Dispatched flight with grounded/expired aircraft without passing A2
        $flight = AviationFlight::create([
            'flight_number' => 'GA-CORRUPT-999',
            'operator_id' => $operator->id,
            'aircraft_id' => $aircraft->id,
            'route_id' => $route->id,
            'origin_airport_id' => $airport1->id,
            'destination_airport_id' => $airport2->id,
            'departure_time' => now()->subHours(2),
            'arrival_time' => now()->subHour(),
            'status' => 'departed', // Illegally departed!
            'fuel_burn_kg' => 3000,
        ]);

        return 'GA-CORRUPT-999';
    }
}
