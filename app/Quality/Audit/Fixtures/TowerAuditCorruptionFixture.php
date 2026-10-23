<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TowerAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'tower:audit';
    }

    public function corrupt(): string
    {
        DB::table('sct_order_promises')->insert([
            'promise_code' => 'PRM-CORRUPT-'.Str::random(5),
            'order_reference' => 'SO-99999',
            'item_code' => 'ITEM-01',
            'requested_qty' => 10,
            'atp_confirmed_qty' => 100, // atp_confirmed_qty > requested_qty (melanggar invariant)
            'ctp_manufacturing_qty' => 0,
            'promised_delivery_date' => now()->addDays(7)->toDateString(),
            'promise_status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'Control tower audit FAILED';
    }
}
