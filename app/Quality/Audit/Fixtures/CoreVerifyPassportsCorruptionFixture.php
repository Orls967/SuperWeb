<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CoreVerifyPassportsCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'core:verify-passports';
    }

    public function corrupt(): string
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Vehicle Owner Test',
            'email' => 'owner-'.Str::random(5).'@example.com',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vehicleId = DB::table('core_vehicles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'vin' => 'VIN-CORRUPT-'.Str::random(5),
            'plate_number' => 'B 9999 CRP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('core_vehicle_events')->insert([
            'vehicle_id' => $vehicleId,
            'sequence' => 1,
            'type' => 'REGISTRATION',
            'actor_id' => $userId,
            'payload' => json_encode(['foo' => 'bar']),
            'prev_hash' => 'GENESIS',
            'hash' => 'corrupted-passport-hash-value',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'RUSAK pada sequence';
    }
}
