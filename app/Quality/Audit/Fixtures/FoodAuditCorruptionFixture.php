<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;

final class FoodAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'food:audit';
    }

    public function corrupt(): string
    {
        DB::table('mfg_agri_batches')->insert([
            'batch_code' => 'BATCH-FIXTURE-CORRUPT-01',
            'target_product_sku' => 'FOOD-CORRUPT-SKU',
            'planned_input_grams' => 500_000,
            'actual_output_grams' => 400_000,
            'waste_grams' => 50_000, // 50,000g discrepancy
            'status' => 'COMPLETED',
            'protein_bps' => 1100,
            'moisture_bps' => 1350,
            'export_grade' => 'GRADE_A_EXPORT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'food.mass_balance_discrepancy';
    }
}
