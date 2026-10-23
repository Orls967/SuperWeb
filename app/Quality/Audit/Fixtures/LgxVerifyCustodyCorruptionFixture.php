<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LgxVerifyCustodyCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'lgx:verify-custody';
    }

    public function corrupt(): string
    {
        $locId = DB::table('lgx_locations')->insertGetId([
            'code' => 'LOC-'.Str::random(4),
            'name' => 'Lokasi Test',
            'type' => 'hub',
            'city' => 'Jakarta',
            'province' => 'DKI',
            'lat_e6' => 0,
            'lng_e6' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => 'Shipper Test',
            'email' => 'shipper-'.Str::random(5).'@example.com',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shipmentId = DB::table('lgx_shipments')->insertGetId([
            'tracking_number' => 'LGX-CHAIN-'.Str::random(5),
            'shipper_id' => $userId,
            'consignee_name' => 'Penerima Test',
            'consignee_phone' => '08123456789',
            'consignee_address' => 'Jl. Test No. 1',
            'origin_location_id' => $locId,
            'destination_location_id' => $locId,
            'service_level' => 'regular',
            'mode' => 'road',
            'status' => 'in_transit',
            'declared_value_idr' => 500000,
            'total_amount_idr' => 25000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lgx_tracking_events')->insert([
            'shipment_id' => $shipmentId,
            'sequence' => 1,
            'event_type' => 'PICKUP_COMPLETED',
            'description' => 'Event dengan hash rantai rusak',
            'occurred_at' => now(),
            'prev_hash' => 'invalid-genesis-hash',
            'hash' => 'invalid-hash-corrupted',
            'created_at' => now(),
        ]);

        return 'pelanggaran integritas rantai lacak balak';
    }
}
