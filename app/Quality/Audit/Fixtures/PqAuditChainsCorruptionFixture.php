<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PqAuditChainsCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'pq:audit-chains';
    }

    public function corrupt(): string
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'PQ Tamperer',
            'email' => 'pq-tamper-'.Str::random(6).'@example.com',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vehicleId = DB::table('core_vehicles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'vin' => 'VIN-PQ-'.Str::random(6),
            'plate_number' => 'B 9999 PQT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('core_vehicle_events')->insert([
            'vehicle_id' => $vehicleId,
            'sequence' => 1,
            'type' => 'TAMPERED_EVENT',
            'actor_id' => $userId,
            'payload' => json_encode(['tampered' => true]),
            'prev_hash' => 'GENESIS',
            'hash' => 'tampered-invalid-hash-value-'.Str::random(8),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'pq:audit-chains FAILED';
    }
}
