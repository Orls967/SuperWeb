<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Ai;

use Illuminate\Support\Str;
use Modules\Core\Application\DTOs\Ai\StockRecommendationResult;
use Modules\Core\Contracts\StockPrescriptionEngineInterface;
use Modules\Core\Domain\Models\CoreAiDecisionSnapshot;
use Modules\Core\Domain\Models\CoreAiStockRecommendation;
use Modules\Shared\Domain\Algorithms\Forecast\SimpleExponentialSmoothing;

class StockPrescriptionEngineService implements StockPrescriptionEngineInterface
{
    private readonly SimpleExponentialSmoothing $ses;

    private readonly DeterministicInferenceEngine $inferenceEngine;

    public function __construct(
        ?SimpleExponentialSmoothing $ses = null,
        ?DeterministicInferenceEngine $inferenceEngine = null
    ) {
        $this->ses = $ses ?? new SimpleExponentialSmoothing;
        $this->inferenceEngine = $inferenceEngine ?? new DeterministicInferenceEngine;
    }

    /**
     * @param  list<int>  $historicalDemands
     */
    public function generateRecommendation(
        string $itemCode,
        string $itemName,
        int $currentStock,
        int $avgDailyDemand,
        int $leadTimeDays,
        int $unitCostIdr,
        string $seasonCode = 'REGULAR',
        float $demandStdDev = 5.0,
        float $leadTimeStdDev = 1.0,
        int $seed = 42,
        array $historicalDemands = []
    ): StockRecommendationResult {
        // 1. Seasonal weighting factor
        $seasonalFactor = match (strtoupper($seasonCode)) {
            'RAMADAN_LEBARAN' => 1.40,
            'YEAR_END_HOLIDAY' => 1.30,
            'RAINY_SEASON' => 1.20,
            default => 1.00,
        };
        $holidayFactorBps = match (strtoupper($seasonCode)) {
            'RAMADAN_LEBARAN' => 14000,
            'YEAR_END_HOLIDAY' => 13000,
            'RAINY_SEASON' => 12000,
            default => 10000,
        };

        if ($historicalDemands !== []) {
            // D64-3 SES dengan alpha 3000 bps
            $sesResult = $this->ses->forecastWithHolidayAdjustment(
                demands: $historicalDemands,
                alphaBps: 3000,
                holidayFactorBps: $holidayFactorBps
            );
            $effectiveDailyDemand = $sesResult->suggestedUnitsPerDay;
            $safetyStock = max(10, (int) ceil($effectiveDailyDemand * 0.35));
            $calculatedRop = ($effectiveDailyDemand * $leadTimeDays) + $safetyStock;

            // Catat ke core_ai_inferences
            $this->inferenceEngine->inferAndRecord(
                modelCode: 'STOCK_SES',
                version: '1.0.0',
                input: [
                    'demands' => $historicalDemands,
                    'alpha_bps' => 3000,
                    'holiday_factor_bps' => $holidayFactorBps,
                ],
                seed: $seed
            );
        } else {
            // Statistical Safety Stock Calculation (95% Service Level => Z = 1.65)
            $z = 1.65;
            $variance = ($leadTimeDays * ($demandStdDev ** 2)) + (($avgDailyDemand ** 2) * ($leadTimeStdDev ** 2));
            $stdDevCombined = sqrt(max(1.0, $variance));
            $safetyStock = (int) ceil($z * $stdDevCombined * $seasonalFactor);
            $leadTimeDemand = $avgDailyDemand * $leadTimeDays;
            $calculatedRop = $leadTimeDemand + $safetyStock;
        }

        // 4. Order Quantity & Autonomous Ladder Threshold
        $suggestedOrderQty = 0;
        if ($currentStock <= $calculatedRop) {
            $deficit = $calculatedRop - $currentStock;
            $economicBuffer = max(10, $avgDailyDemand * 7);
            $suggestedOrderQty = $deficit + $economicBuffer;
        }

        $estimatedCostIdr = $suggestedOrderQty * $unitCostIdr;

        // Plafon Otonom Level 2: Rp 10.000.000. Di atas plafon mewajibkan approval pengadaan.
        $requiresHumanApproval = $estimatedCostIdr > 10_000_000;

        $decisionCode = 'DEC-STK-'.Str::upper(Str::random(10));
        $snapshotId = (string) Str::uuid();

        $inputSnapshot = [
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'current_stock' => $currentStock,
            'avg_daily_demand' => $avgDailyDemand,
            'lead_time_days' => $leadTimeDays,
            'unit_cost_idr' => $unitCostIdr,
            'season_code' => $seasonCode,
            'demand_std_dev' => $demandStdDev,
            'lead_time_std_dev' => $leadTimeStdDev,
            'historical_demands' => $historicalDemands,
        ];

        $decisionOutput = [
            'calculated_rop' => $calculatedRop,
            'safety_stock' => $safetyStock,
            'suggested_order_qty' => $suggestedOrderQty,
            'seasonal_factor' => $seasonalFactor,
            'estimated_cost_idr' => $estimatedCostIdr,
            'requires_human_approval' => $requiresHumanApproval,
            'parameters' => [
                'seasonal_factor' => $seasonalFactor,
                'lead_time_days' => $leadTimeDays,
            ],
        ];

        $decisionHash = hash('sha256', (string) $seed.json_encode($inputSnapshot).json_encode($decisionOutput));

        CoreAiDecisionSnapshot::create([
            'id' => $snapshotId,
            'decision_code' => $decisionCode,
            'model_name' => 'STOCK_PRESCRIPTION_ENGINE',
            'model_version' => '1.0.0',
            'seed' => (string) $seed,
            'input_snapshot' => $inputSnapshot,
            'decision_output' => $decisionOutput,
            'decision_hash' => $decisionHash,
        ]);

        CoreAiStockRecommendation::create([
            'id' => (string) Str::uuid(),
            'recommendation_code' => 'REC-'.Str::upper(Str::random(8)),
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'current_stock' => $currentStock,
            'calculated_rop' => $calculatedRop,
            'safety_stock' => $safetyStock,
            'suggested_order_qty' => $suggestedOrderQty,
            'seasonal_factor' => $seasonalFactor,
            'estimated_cost_idr' => $estimatedCostIdr,
            'requires_human_approval' => $requiresHumanApproval,
            'decision_snapshot_id' => $snapshotId,
            'status' => 'PROPOSED',
        ]);

        return new StockRecommendationResult(
            itemCode: $itemCode,
            itemName: $itemName,
            currentStock: $currentStock,
            calculatedRop: $calculatedRop,
            safetyStock: $safetyStock,
            suggestedOrderQty: $suggestedOrderQty,
            seasonalFactor: (float) $seasonalFactor,
            estimatedCostIdr: $estimatedCostIdr,
            requiresHumanApproval: $requiresHumanApproval,
            status: 'PROPOSED',
            parameters: [
                'seasonal_factor' => $seasonalFactor,
                'calculated_rop' => $calculatedRop,
            ],
            decisionHash: $decisionHash,
            decisionCode: $decisionCode,
        );
    }
}
