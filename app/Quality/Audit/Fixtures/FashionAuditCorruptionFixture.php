<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;

final class FashionAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'fsh:audit';
    }

    public function corrupt(): string
    {
        $now = now();
        $collectionId = DB::table('fsh_collections')->insertGetId([
            'collection_code' => 'COL-CORRUPT-01',
            'name' => 'Koleksi Rusak Uji Audit',
            'season' => 'FW26',
            'year' => 2026,
            'status' => 'DRAFT',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sampleId = DB::table('fsh_samples')->insertGetId([
            'collection_id' => $collectionId,
            'sample_code' => 'SMP-CORRUPT-01',
            'article_name' => 'Kemeja Rusak',
            'approval_status' => 'PENDING', // Belum approved!
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $planId = DB::table('fsh_buy_plans')->insertGetId([
            'collection_id' => $collectionId,
            'plan_code' => 'BUY-CORRUPT-01',
            'target_total_units' => 1000,
            'budget_idr' => 100_000_000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $orderId = DB::table('fsh_production_orders')->insertGetId([
            'buy_plan_id' => $planId,
            'sample_id' => $sampleId,
            'po_number' => 'PO-CORRUPT-01',
            'factory_name' => 'Pabrik Garmen Korupsi',
            'total_ordered_units' => 1000,
            'total_cost_idr' => 100_000_000,
            'status' => 'ISSUED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Alokasi dibuat selisih: total ordered 1000 tapi alokasi hanya 900
        DB::table('fsh_production_allocations')->insert([
            'production_order_id' => $orderId,
            'size_code' => 'M',
            'color_code' => 'BLACK',
            'allocated_units' => 900,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 'fsh.allocation_conservation_mismatch';
    }
}
