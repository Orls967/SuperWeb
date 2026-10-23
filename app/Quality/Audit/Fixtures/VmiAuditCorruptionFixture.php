<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;

final class VmiAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'vmi:audit';
    }

    public function corrupt(): string
    {
        DB::table('mfg_vmi_replenishments')->insert([
            'vmi_code' => 'VMI-CORRUPT-FIXTURE',
            'supplier_id' => 9999,
            'sku' => 'SKU-CORRUPT-VMI',
            'current_shelf_stock' => 5,
            'reorder_point' => 10,
            'suggested_reorder_qty' => 100,
            'total_estimated_idr' => 100_000_000,
            'contract_plafond_idr' => 50_000_000, // estimated > plafond but status is AUTO_ORDERED (violation)
            'status' => 'AUTO_ORDERED',
            'idempotency_key' => 'VMI:9999:CORRUPT:FIXTURE',
            'created_at' => '2026-10-19 12:00:00',
            'updated_at' => '2026-10-19 12:00:00',
        ]);

        return 'vmi.auto_order_over_plafond';
    }
}
