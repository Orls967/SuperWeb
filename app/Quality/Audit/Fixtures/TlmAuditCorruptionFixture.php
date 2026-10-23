<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Models\User;
use App\Quality\Audit\CorruptionFixture;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Telematics\Domain\Models\TelematicsDevice;

final class TlmAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'tlm:audit';
    }

    public function corrupt(): string
    {
        $vehicle = Vehicle::first();
        if (! $vehicle) {
            $user = User::firstOrCreate(['email' => 'corrupt_driver@example.com'], ['name' => 'Corrupt Driver', 'password' => bcrypt('secret')]);
            $vehicle = Vehicle::create([
                'user_id' => $user->id,
                'plate_number' => 'B 9999 CRP',
                'vin' => 'VIN-CORRUPT-'.Str::random(6),
                'color' => 'Red',
                'odometer_km' => 1000,
                'status' => 'active',
            ]);
        }

        // Corrupt device: status is 'grounded' but no critical DTC tick exists!
        TelematicsDevice::create([
            'device_id' => 'DEV-CORRUPT-'.Str::random(4),
            'vehicle_id' => $vehicle->id,
            'serial_number' => 'SN-CORRUPT-001',
            'protocol' => 'MQTT',
            'status' => 'grounded',
            'last_heartbeat_at' => Carbon::now(),
        ]);

        return 'Telematics audit FAILED';
    }
}
