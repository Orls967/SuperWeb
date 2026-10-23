<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TradeAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'trade:audit';
    }

    public function corrupt(): string
    {
        $refNo = 'EXP-CORRUPT-'.Str::random(5);

        DB::table('trd_shipment_legs')->insert([
            'id' => (string) Str::uuid(),
            'order_reference_no' => $refNo,
            'leg_stage' => 'CUSTOMS_DEPARTURE',
            'location_name' => 'Tanjung Priok Port',
            'notes' => 'Rantai rusak fixture',
            'previous_hash' => 'GENESIS',
            'hash' => 'invalid-corrupted-hash',
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'Trade audit FAILED';
    }
}
