<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FoodAgriProcessingT1Seeder extends Seeder
{
    /**
     * D168 Simulasi T1:
     * - 500 lot panen Agri
     * - 100 batch pengolahan Manufacturing (neraca massa klop)
     * - 20 merek & 50 produk Food dengan batch nutrition
     */
    public function run(): void
    {
        $now = '2026-11-04 00:00:00';

        // Bersihkan data T1 sebelumnya jika ada
        DB::table('food_batch_nutrition')->delete();
        DB::table('mfg_agri_genealogy_edges')->delete();
        DB::table('mfg_agri_batch_inputs')->delete();
        DB::table('mfg_agri_batches')->delete();
        DB::table('food_products')->delete();
        DB::table('food_brands')->delete();
        DB::table('agri_collection_consumptions')->delete();
        DB::table('agri_collection_batches')->where('batch_number', 'like', 'AGRI-LOT-T1-%')->delete();
        DB::table('agri_contracts')->where('contract_number', 'like', 'CTR-T1-%')->delete();
        DB::table('agri_farmers')->where('farmer_code', 'like', 'FARMER-T1-%')->delete();

        // 1. Petani & Kontrak Agri (10 petani, 10 kontrak)
        $contractIds = [];

        for ($f = 1; $f <= 10; $f++) {
            $fId = (string) Str::uuid();
            DB::table('agri_farmers')->insert([
                'id' => $fId,
                'farmer_code' => sprintf('FARMER-T1-%03d', $f),
                'full_name' => "Petani Binaan {$f}",
                'farmer_group_name' => 'Gapoktan Harapan Makmur',
                'phone_number' => sprintf('081234567%02d', $f),
                'land_area_hectares' => 10.0,
                'primary_commodity' => 'jagung',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $cId = (string) Str::uuid();
            $contractIds[] = $cId;
            DB::table('agri_contracts')->insert([
                'id' => $cId,
                'contract_number' => sprintf('CTR-T1-%03d', $f),
                'farmer_id' => $fId,
                'commodity' => 'jagung',
                'planting_date' => '2026-08-01',
                'expected_harvest_date' => '2026-11-01',
                'target_yield_kg' => 100000.00,
                'seed_advance_value_idr' => 0,
                'fertilizer_advance_value_idr' => 0,
                'total_advance_deductible_idr' => 0,
                'guaranteed_floor_price_idr_per_kg' => 8000,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. 500 Lot Panen Agri
        $harvestLotIds = [];
        $harvestRows = [];

        for ($h = 1; $h <= 500; $h++) {
            $hId = (string) Str::uuid();
            $harvestLotIds[] = $hId;
            $contractId = $contractIds[($h - 1) % 10];
            $weightKg = 500.00; // 500 kg per lot
            $pricePerKg = 8000;
            $payout = (int) ($weightKg * $pricePerKg);

            $harvestRows[] = [
                'id' => $hId,
                'batch_number' => sprintf('AGRI-LOT-T1-%04d', $h),
                'collection_center_id' => 'CC-TALA-01',
                'contract_id' => $contractId,
                'received_date' => '2026-11-02',
                'gross_weight_kg' => $weightKg,
                'grade' => ($h % 3 === 0) ? 'GRADE_B' : 'GRADE_A',
                'moisture_percentage' => 13.50,
                'buying_price_per_kg' => $pricePerKg,
                'gross_payout_idr' => $payout,
                'advance_deduction_idr' => 0,
                'net_payout_idr' => $payout,
                'payment_status' => 'paid',
                'destination_unit' => 'MFG_PLANT_01',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($harvestRows, 100) as $chunk) {
            DB::table('agri_collection_batches')->insert($chunk);
        }

        // 3. 20 Merek & 50 Produk Food
        $brandIds = [];
        for ($b = 1; $b <= 20; $b++) {
            $brandId = DB::table('food_brands')->insertGetId([
                'name' => "AgriFood Nusantara {$b}",
                'code' => sprintf('BRD-T1-%02d', $b),
                'description' => 'Merek pangan hasil pengolahan pertanian Nusantara',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $brandIds[] = $brandId;
        }

        $productIds = [];
        $productSkus = [];
        for ($p = 1; $p <= 50; $p++) {
            $brandId = $brandIds[($p - 1) % 20];
            $sku = sprintf('SKU-FOOD-T1-%03d', $p);
            $productSkus[] = $sku;
            $productId = DB::table('food_products')->insertGetId([
                'brand_id' => $brandId,
                'sku' => $sku,
                'name' => "Tepung Jagung Olahan {$p}",
                'category' => 'Tepung Olahan',
                'standard_package_grams' => 1000,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $productIds[] = $productId;
        }

        // 4. 100 Batch Pengolahan Manufacturing & Genealogi
        for ($m = 1; $m <= 100; $m++) {
            $batchCode = sprintf('MFG-AGRI-T1-%04d', $m);
            $sku = $productSkus[($m - 1) % 50];
            $prodId = $productIds[($m - 1) % 50];

            // 5 lot panen per batch = 5 x 500 kg = 2.500 kg = 2.500.000 grams
            $inputGrams = 2_500_000;
            $wasteGrams = 250_000; // 10% waste
            $outputGrams = 2_250_000; // 90% output (2.250.000 + 250.000 = 2.500.000 -> massa klop)

            $batchId = DB::table('mfg_agri_batches')->insertGetId([
                'batch_code' => $batchCode,
                'target_product_sku' => $sku,
                'planned_input_grams' => $inputGrams,
                'actual_output_grams' => $outputGrams,
                'waste_grams' => $wasteGrams,
                'status' => 'COMPLETED',
                'protein_bps' => 1250, // 12.5%
                'moisture_bps' => 1300, // 13.0%
                'export_grade' => 'EXPORT_GRADE_A',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 5 input edges & inputs
            for ($k = 0; $k < 5; $k++) {
                $harvestIndex = (($m - 1) * 5 + $k) % 500;
                $lotId = $harvestLotIds[$harvestIndex];

                DB::table('mfg_agri_batch_inputs')->insert([
                    'mfg_agri_batch_id' => $batchId,
                    'agri_collection_batch_id' => $lotId,
                    'allocated_grams' => 500_000,
                    'cost_idr' => 4_000_000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('mfg_agri_genealogy_edges')->insert([
                    'parent_entity_type' => 'HARVEST_LOT',
                    'parent_entity_id' => $lotId,
                    'child_entity_type' => 'BATCH',
                    'child_entity_id' => (string) $batchId,
                    'quantity_grams' => 500_000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Output edge to food product
            DB::table('mfg_agri_genealogy_edges')->insert([
                'parent_entity_type' => 'BATCH',
                'parent_entity_id' => (string) $batchId,
                'child_entity_type' => 'PRODUCT_ITEM',
                'child_entity_id' => (string) $prodId,
                'quantity_grams' => $outputGrams,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 5. Batch Nutrition Facts
            DB::table('food_batch_nutrition')->insert([
                'batch_id' => $batchId,
                'food_product_id' => $prodId,
                'calories_kcal' => 365,
                'protein_grams' => 12.5,
                'fat_grams' => 1.5,
                'carbs_grams' => 74.0,
                'sodium_mg' => 35,
                'serving_size_grams' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
