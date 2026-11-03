<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;

class FashionTextileT1Seeder extends Seeder
{
    /**
     * D181 Simulasi T1:
     * - 20 koleksi musiman
     * - 50 sampel artikel (APPROVED)
     * - 20 rencana beli
     * - 100 PO produksi dengan alokasi diskrit ukuran/warna klop (2.000 varian)
     */
    public function run(): void
    {
        $now = '2026-11-04 00:00:00';

        // Bersihkan data T1
        DB::table('fsh_production_allocations')->delete();
        DB::table('fsh_production_orders')->delete();
        DB::table('fsh_buy_plans')->delete();
        DB::table('fsh_samples')->delete();
        DB::table('fsh_collections')->delete();

        $apportioner = new LargestRemainder;

        // 1. 20 Koleksi Musiman
        $collectionIds = [];
        $seasons = ['SS26', 'FW26', 'RESORT26', 'PREFALL26'];
        for ($c = 1; $c <= 20; $c++) {
            $season = $seasons[($c - 1) % 4];
            $colId = DB::table('fsh_collections')->insertGetId([
                'collection_code' => sprintf('COL-T1-%03d', $c),
                'name' => "Koleksi Busana Nusantara {$season} Seri {$c}",
                'season' => $season,
                'year' => 2026,
                'status' => 'APPROVED',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $collectionIds[] = $colId;
        }

        // 2. 50 Sampel Artikel
        $sampleIds = [];
        for ($s = 1; $s <= 50; $s++) {
            $colId = $collectionIds[($s - 1) % 20];
            $sampleId = DB::table('fsh_samples')->insertGetId([
                'collection_id' => $colId,
                'sample_code' => sprintf('SMP-T1-%03d', $s),
                'article_name' => "Kemeja Tenun Kontemporer Model {$s}",
                'tech_pack_ref' => sprintf('TP-2026-%04d', $s),
                'approval_status' => 'APPROVED',
                'approved_at' => '2026-11-01 10:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sampleIds[] = $sampleId;
        }

        // 3. 20 Rencana Beli
        $buyPlanIds = [];
        for ($b = 1; $b <= 20; $b++) {
            $colId = $collectionIds[$b - 1];
            $planId = DB::table('fsh_buy_plans')->insertGetId([
                'collection_id' => $colId,
                'plan_code' => sprintf('BUY-T1-%03d', $b),
                'target_total_units' => 10_000,
                'budget_idr' => 1_500_000_000,
                'status' => 'APPROVED',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $buyPlanIds[] = $planId;
        }

        // 4. 100 PO Produksi & 2.000 Varian Alokasi
        // Tiap PO memiliki 4 warna x 5 ukuran (XS, S, M, L, XL) = 20 alokasi.
        // 100 PO x 20 alokasi = 2.000 varian alokasi.
        $colors = ['BLACK', 'NAVY', 'MAROON', 'OLIVE'];
        $sizeWeights = [
            'XS' => 1,
            'S' => 2,
            'M' => 3,
            'L' => 3,
            'XL' => 1,
        ];
        $sizeItems = [];
        foreach ($sizeWeights as $sz => $wt) {
            $sizeItems[] = ['key' => $sz, 'weight' => $wt];
        }

        $colorItems = [];
        foreach ($colors as $clr) {
            $colorItems[] = ['key' => $clr, 'weight' => 1];
        }

        $orderRows = [];
        $allocationRows = [];

        for ($p = 1; $p <= 100; $p++) {
            $planId = $buyPlanIds[($p - 1) % 20];
            $sampleId = $sampleIds[($p - 1) % 50];
            $totalUnits = 1000;
            $unitCostIdr = 150_000;
            $totalCostIdr = $totalUnits * $unitCostIdr;

            $orderId = DB::table('fsh_production_orders')->insertGetId([
                'buy_plan_id' => $planId,
                'sample_id' => $sampleId,
                'po_number' => sprintf('PO-FSH-T1-%04d', $p),
                'factory_name' => 'PT Srirejeki Garmen Utama',
                'total_ordered_units' => $totalUnits,
                'total_cost_idr' => $totalCostIdr,
                'status' => 'IN_PRODUCTION',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Alokasikan unit ke warna menggunakan LargestRemainder
            $colorAllocations = $apportioner->apportion($totalUnits, $colorItems);

            foreach ($colorAllocations as $clr => $clrUnits) {
                $sizeAllocations = $apportioner->apportion($clrUnits, $sizeItems);

                foreach ($sizeAllocations as $sz => $u) {
                    $allocationRows[] = [
                        'production_order_id' => $orderId,
                        'size_code' => $sz,
                        'color_code' => $clr,
                        'allocated_units' => $u,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($allocationRows, 200) as $chunk) {
            DB::table('fsh_production_allocations')->insert($chunk);
        }
    }
}
