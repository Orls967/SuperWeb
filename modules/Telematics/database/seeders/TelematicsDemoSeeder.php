<?php

declare(strict_types=1);

namespace Modules\Telematics\database\seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Telematics\Domain\Models\TelematicsAggregate;
use Modules\Telematics\Domain\Models\TelematicsBaseline;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Modules\Telematics\Domain\Models\TelematicsTick;

class TelematicsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'telematics_owner@example.com'],
            ['name' => 'Telematics Fleet Driver', 'password' => bcrypt('secret123')]
        );

        $vehicle = Vehicle::firstOrCreate(
            ['vin' => 'VIN-DEMO-TLM-001'],
            [
                'user_id' => $user->id,
                'plate_number' => 'B 1234 TLM',
                'color' => 'Black',
                'odometer_km' => 15000,
                'status' => 'active',
            ]
        );

        $device = TelematicsDevice::firstOrCreate(
            ['device_id' => 'DEV-OBD2-001'],
            [
                'vehicle_id' => $vehicle->id,
                'serial_number' => 'SN-TLM-889900',
                'protocol' => 'MQTT_CANBUS',
                'status' => 'online',
                'last_heartbeat_at' => Carbon::now(),
            ]
        );

        TelematicsBaseline::firstOrCreate(
            ['vehicle_id' => $vehicle->id],
            [
                'avg_oil_temp_c' => 90.0,
                'avg_battery_voltage' => 12.6,
                'avg_fuel_consumption_rate' => 8.5,
                'sample_count' => 100,
                'calculated_at' => Carbon::now(),
            ]
        );

        TelematicsTick::firstOrCreate(
            ['device_id' => $device->device_id, 'sequence' => 1],
            [
                'vehicle_id' => $vehicle->id,
                'recorded_at' => Carbon::now(),
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'rpm' => 2200,
                'speed_kmh' => 65.0,
                'oil_temp_c' => 92.0,
                'battery_voltage' => 12.5,
                'fuel_level_pct' => 80.0,
                'idempotency_key' => hash('sha256', "{$device->device_id}:1:seed"),
            ]
        );

        $now = Carbon::now();
        $minuteBucket = (int) (floor($now->minute / 5) * 5);
        $wStart = $now->copy()->minute($minuteBucket)->second(0);

        TelematicsAggregate::firstOrCreate(
            [
                'device_id' => $device->device_id,
                'window_start' => $wStart,
            ],
            [
                'vehicle_id' => $vehicle->id,
                'window_end' => (clone $wStart)->addMinutes(5),
                'avg_speed_kmh' => 65.0,
                'max_rpm' => 2200,
                'avg_oil_temp_c' => 92.0,
                'min_battery_voltage' => 12.5,
                'tick_count' => 1,
            ]
        );
    }
}
