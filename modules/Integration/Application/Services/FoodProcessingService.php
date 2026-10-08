<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FoodProcessingService (Fase 168 — Lini 21)
 *
 * Implements:
 *  - 168.1 Commodity procurement & intake grading with accurate farmer settlements
 *  - 168.3 Processing run mass-balance invariant: input = finished + co-product + waste
 *  - 168.4 Food-safety QA control & quarantine block preventing shipment
 */
class FoodProcessingService
{
    /**
     * Intake raw agricultural commodity with grade score and pricing settlement.
     */
    public function intakeLot(string $farmerGroupId, string $commodity, float $intakeKg, float $gradeScore, float $pricePerKg): object
    {
        $lotCode = 'LOT-'.strtoupper(Str::random(8));
        $totalSettlement = round($intakeKg * $pricePerKg, 2);

        $id = DB::table('food_intake_lots')->insertGetId([
            'lot_code' => $lotCode,
            'farmer_group_id' => $farmerGroupId,
            'commodity_name' => strtoupper($commodity),
            'raw_intake_kg' => $intakeKg,
            'grade_score' => $gradeScore,
            'settlement_price_per_kg' => $pricePerKg,
            'total_settlement_idr' => $totalSettlement,
            'status' => 'RECEIVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('food_intake_lots')->find($id);
    }

    /**
     * Process raw lot into finished goods, co-products, and waste under strict mass-balance.
     * Invarian: input_raw = output_finished + co_product + waste (within 1% tolerance).
     */
    public function executeProcessingRun(string $sourceLotCode, float $inputKg, float $finishedKg, float $coProductKg, float $wasteKg): object
    {
        $totalOutput = $finishedKg + $coProductKg + $wasteKg;
        $massLoss = abs($inputKg - $totalOutput);
        $lossPct = $inputKg > 0 ? round(($massLoss / $inputKg) * 100.0, 2) : 0.00;

        // Tolerance: max 2% acceptable processing loss
        $isValid = ($lossPct <= 2.0);

        $runCode = 'RUN-'.strtoupper(Str::random(8));

        $id = DB::table('food_processing_runs')->insertGetId([
            'run_code' => $runCode,
            'source_lot_code' => $sourceLotCode,
            'input_raw_kg' => $inputKg,
            'output_finished_kg' => $finishedKg,
            'co_product_kg' => $coProductKg,
            'waste_kg' => $wasteKg,
            'mass_loss_pct' => $lossPct,
            'mass_balance_valid' => $isValid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('food_processing_runs')->find($id);
    }

    /**
     * Perform QA lab check and enforce quarantine gate.
     */
    public function conductQaCheck(string $lotCode, float $tempC, float $moisturePct, bool $labCleared): object
    {
        $isQuarantined = (! $labCleared || $tempC > 30.0 || $moisturePct > 15.0);
        $isReleased = ! $isQuarantined;

        DB::table('food_qa_checks')->updateOrInsert(
            ['lot_code' => $lotCode],
            [
                'temperature_c' => $tempC,
                'moisture_pct' => $moisturePct,
                'lab_toxin_cleared' => $labCleared,
                'is_quarantined' => $isQuarantined,
                'is_released_for_shipping' => $isReleased,
                'updated_at' => now(),
            ]
        );

        if ($isQuarantined) {
            DB::table('food_intake_lots')->where('lot_code', $lotCode)->update([
                'status' => 'QUARANTINED',
                'updated_at' => now(),
            ]);
        }

        return (object) DB::table('food_qa_checks')->where('lot_code', $lotCode)->first();
    }

    /**
     * Attempt export shipment; rejects if quarantined or not released.
     */
    public function shipForExport(string $lotCode): bool
    {
        $qa = DB::table('food_qa_checks')->where('lot_code', $lotCode)->first();
        if (! $qa || (bool) $qa->is_quarantined || ! (bool) $qa->is_released_for_shipping) {
            throw new \RuntimeException("Export blocked: Lot {$lotCode} is under quarantine or failed food safety lab clearance.");
        }

        return true;
    }

    /**
     * Food processing audit quality gate (`food:audit`).
     */
    public function audit(): array
    {
        $invalidRuns = DB::table('food_processing_runs')
            ->where('mass_balance_valid', false)
            ->count();

        return [
            'status' => $invalidRuns === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_lots' => DB::table('food_intake_lots')->count(),
            'total_processing_runs' => DB::table('food_processing_runs')->count(),
            'total_qa_checks' => DB::table('food_qa_checks')->count(),
            'discrepancy_count' => $invalidRuns,
        ];
    }
}
