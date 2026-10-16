<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WmsAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'wms:audit';
    }

    public function corrupt(): string
    {
        $whId = (string) Str::uuid();
        DB::table('wms_warehouses')->insert([
            'id' => $whId,
            'code' => 'WH-'.Str::random(4),
            'name' => 'Gudang Uji',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferId = (string) Str::uuid();
        DB::table('wms_transfers')->insert([
            'id' => $transferId,
            'number' => 'TRF-'.Str::random(5),
            'from_warehouse_id' => $whId,
            'to_warehouse_id' => $whId,
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wms_transfer_lines')->insert([
            'transfer_id' => $transferId,
            'product_id' => 1,
            'qty' => 10,
            'status' => 'open', // bukan received
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'transfer.diterima';
    }
}
