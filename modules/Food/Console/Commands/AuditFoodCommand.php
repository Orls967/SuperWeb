<?php

declare(strict_types=1);

namespace Modules\Food\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditFoodCommand extends Command
{
    protected $signature = 'food:audit {--json : Format output sebagai JSON}';

    protected $description = 'Audit dua sumber integritas komoditas pangan, neraca massa pengolahan, dan mutu ekspor (Lini 21)';

    public function handle(): int
    {
        $this->info('Memulai audit dua sumber komoditas pangan dan pengolahan...');
        $discrepancies = [];

        // 1. Invarian neraca massa batch manufaktur pengolahan: input = output + waste
        if (Schema::hasTable('mfg_agri_batches')) {
            $batches = DB::select("SELECT batch_code, planned_input_grams, actual_output_grams, waste_grams, status, export_grade, protein_bps, moisture_bps FROM mfg_agri_batches WHERE status IN ('COMPLETED', 'EXPORT_CERTIFIED')");
            foreach ($batches as $batch) {
                $plannedInput = (int) $batch->planned_input_grams;
                $outputGrams = (int) $batch->actual_output_grams;
                $wasteGrams = (int) $batch->waste_grams;
                $expectedInput = $outputGrams + $wasteGrams;

                if ($plannedInput !== $expectedInput) {
                    $discrepancies[] = [
                        'code' => 'food.mass_balance_discrepancy',
                        'detail' => "Batch {$batch->batch_code}: planned_input={$plannedInput}g vs output+waste={$expectedInput}g (selisih ".(abs($plannedInput - $expectedInput)).'g)',
                    ];
                }

                // Validasi parameter export grade
                if ($batch->status === 'EXPORT_CERTIFIED' || $batch->export_grade === 'GRADE_A_EXPORT') {
                    if ((int) $batch->protein_bps < 1000 || (int) $batch->moisture_bps > 1400) {
                        $discrepancies[] = [
                            'code' => 'food.export_grade_parameter_violation',
                            'detail' => "Batch {$batch->batch_code}: protein={$batch->protein_bps} bps (min 1000), moisture={$batch->moisture_bps} bps (max 1400)",
                        ];
                    }
                }
            }
        }

        // 2. Konsumsi lot panen tidak boleh melebihi berat bruto panen
        if (Schema::hasTable('agri_collection_consumptions') && Schema::hasTable('agri_collection_batches')) {
            $consumptions = DB::select('
                SELECT c.collection_batch_id, SUM(c.grams_consumed) as total_consumed, b.batch_number, b.gross_weight_kg
                FROM agri_collection_consumptions c
                JOIN agri_collection_batches b ON b.id = c.collection_batch_id
                GROUP BY c.collection_batch_id, b.batch_number, b.gross_weight_kg
            ');

            foreach ($consumptions as $c) {
                $maxGrams = (int) round(((float) $c->gross_weight_kg) * 1000);
                if ((int) $c->total_consumed > $maxGrams) {
                    $discrepancies[] = [
                        'code' => 'food.agri_lot_overconsumption',
                        'detail' => "Lot panen {$c->batch_number}: dikonsumsi {$c->total_consumed}g melebihi kapasitas bruto {$maxGrams}g",
                    ];
                }
            }
        }

        // 3. Integritas merek dan produk pangan (tabel Food sendiri)
        if (Schema::hasTable('food_products') && Schema::hasTable('food_brands')) {
            $orphanProducts = DB::table('food_products')
                ->leftJoin('food_brands', 'food_products.brand_id', '=', 'food_brands.id')
                ->whereNull('food_brands.id')
                ->count();

            if ($orphanProducts > 0) {
                $discrepancies[] = [
                    'code' => 'food.orphan_product_brand',
                    'detail' => "Ditemukan {$orphanProducts} produk pangan tanpa merek terdaftar",
                ];
            }
        }

        if (count($discrepancies) > 0) {
            if ($this->option('json')) {
                $this->line(json_encode([
                    'status' => 'FAIL',
                    'discrepancy_count' => count($discrepancies),
                    'discrepancies' => $discrepancies,
                ], JSON_PRETTY_PRINT));
            } else {
                $this->error('Ditemukan '.count($discrepancies).' ketidaksesuaian audit pangan:');
                foreach ($discrepancies as $d) {
                    $this->line("  [{$d['code']}] {$d['detail']}");
                }
            }

            return 1;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => 'OK',
                'discrepancy_count' => 0,
                'discrepancies' => [],
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info('Audit dua sumber komoditas pangan & pengolahan BERSIH (0 selisih).');
        }

        return 0;
    }
}
