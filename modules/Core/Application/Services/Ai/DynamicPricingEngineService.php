<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Ai;

use Illuminate\Support\Str;
use Modules\Core\Application\DTOs\Ai\DynamicPricingResult;
use Modules\Core\Contracts\DynamicPricingEngineInterface;
use Modules\Core\Domain\Models\CoreAiDecisionSnapshot;
use Modules\Core\Domain\Models\CoreAiPricingRule;

class DynamicPricingEngineService implements DynamicPricingEngineInterface
{
    public function calculatePrice(
        string $itemCode,
        int $basePriceIdr,
        int $currentStock,
        ?int $hoursToExpiry = null,
        float $spaceOccupancyRate = 0.50,
        int $recentSalesVelocity = 10,
        int $baselineSalesVelocity = 10,
        int $seed = 42
    ): DynamicPricingResult {
        // 1. Fetch active pricing rule or fallback to deterministic guardrails
        $rule = CoreAiPricingRule::where('item_code', $itemCode)->where('is_active', true)->first();

        $floorPriceIdr = $rule?->floor_price_idr ?? (int) round($basePriceIdr * 0.70);
        $ceilingPriceIdr = $rule?->ceiling_price_idr ?? (int) round($basePriceIdr * 1.50);
        $elasticity = $rule?->elasticity_coefficient ?? -0.50;
        $maxSurge = $rule?->max_surge_multiplier ?? 1.50;
        $decayHours = $rule?->decay_hours_threshold ?? 24;

        // 2. Demand sensitivity multiplier (higher demand => higher price, lower demand => discount)
        $safeBaseline = max(1, $baselineSalesVelocity);
        $demandShock = ($recentSalesVelocity - $safeBaseline) / $safeBaseline;
        $sensitivity = abs($elasticity);
        $demandMultiplier = 1.0 + ($sensitivity * $demandShock);
        $demandMultiplier = max(0.50, min(1.80, $demandMultiplier));

        // 3. Shelf-life decay multiplier
        $decayMultiplier = 1.00;
        if ($hoursToExpiry !== null && $hoursToExpiry <= $decayHours) {
            $hoursLeft = max(0, $hoursToExpiry);
            $fraction = (24.0 - $hoursLeft) / 24.0;
            $decayMultiplier = max(0.50, 1.00 - ($fraction * 0.50));
        }

        // 4. Occupancy surge multiplier
        $surgeMultiplier = 1.00;
        if ($spaceOccupancyRate > 0.70) {
            $excessOccupancy = $spaceOccupancyRate - 0.70;
            $surgeMultiplier = min($maxSurge, 1.00 + ($excessOccupancy * 0.50));
        }

        // 5. Raw Price Calculation
        $totalMultiplier = $demandMultiplier * $decayMultiplier * $surgeMultiplier;
        $rawPrice = $basePriceIdr * $totalMultiplier;

        // 6. Rounding to nearest Rp 100 and Guardrail Clamping
        $roundedPrice = (int) (round($rawPrice / 100.0) * 100);
        $finalPriceIdr = min($ceilingPriceIdr, max($floorPriceIdr, $roundedPrice));

        $breakdown = [
            'demand_shock' => round($demandShock, 4),
            'demand_multiplier' => round($demandMultiplier, 4),
            'decay_multiplier' => round($decayMultiplier, 4),
            'surge_multiplier' => round($surgeMultiplier, 4),
            'raw_price_idr' => (int) round($rawPrice),
            'floor_guardrail_applied' => $roundedPrice < $floorPriceIdr,
            'ceiling_guardrail_applied' => $roundedPrice > $ceilingPriceIdr,
        ];

        // 7. Deterministic Snapshotting with SHA-256
        $decisionCode = 'DEC-PRC-'.Str::upper(Str::random(10));
        $inputSnapshot = [
            'item_code' => $itemCode,
            'base_price_idr' => $basePriceIdr,
            'current_stock' => $currentStock,
            'hours_to_expiry' => $hoursToExpiry,
            'space_occupancy_rate' => $spaceOccupancyRate,
            'recent_sales_velocity' => $recentSalesVelocity,
            'baseline_sales_velocity' => $baselineSalesVelocity,
            'floor_price_idr' => $floorPriceIdr,
            'ceiling_price_idr' => $ceilingPriceIdr,
        ];

        $decisionOutput = [
            'final_price_idr' => $finalPriceIdr,
            'multiplier' => round($totalMultiplier, 4),
            'breakdown' => $breakdown,
        ];

        $decisionHash = hash('sha256', (string) $seed.json_encode($inputSnapshot).json_encode($decisionOutput));

        CoreAiDecisionSnapshot::create([
            'id' => (string) Str::uuid(),
            'decision_code' => $decisionCode,
            'model_name' => 'DYNAMIC_PRICING',
            'model_version' => '1.0.0',
            'seed' => (string) $seed,
            'input_snapshot' => $inputSnapshot,
            'decision_output' => $decisionOutput,
            'decision_hash' => $decisionHash,
        ]);

        return new DynamicPricingResult(
            itemCode: $itemCode,
            finalPriceIdr: $finalPriceIdr,
            basePriceIdr: $basePriceIdr,
            floorPriceIdr: $floorPriceIdr,
            ceilingPriceIdr: $ceilingPriceIdr,
            multiplier: round($totalMultiplier, 4),
            breakdown: $breakdown,
            decisionHash: $decisionHash,
            decisionCode: $decisionCode,
        );
    }
}
