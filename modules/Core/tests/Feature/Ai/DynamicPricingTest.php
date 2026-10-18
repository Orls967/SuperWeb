<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\Ai\DynamicPricingEngineService;
use Modules\Core\Domain\Models\CoreAiDecisionSnapshot;
use Modules\Core\Domain\Models\CoreAiPricingRule;

uses(RefreshDatabase::class);

test('calculates deterministic dynamic prices with identical hash for identical seed and inputs', function (): void {
    $engine = new DynamicPricingEngineService;

    $run1 = $engine->calculatePrice(
        itemCode: 'MENU-NASI-GORENG',
        basePriceIdr: 30000,
        currentStock: 50,
        hoursToExpiry: 12,
        spaceOccupancyRate: 0.85,
        recentSalesVelocity: 25,
        baselineSalesVelocity: 15,
        seed: 9999
    );

    $run2 = $engine->calculatePrice(
        itemCode: 'MENU-NASI-GORENG',
        basePriceIdr: 30000,
        currentStock: 50,
        hoursToExpiry: 12,
        spaceOccupancyRate: 0.85,
        recentSalesVelocity: 25,
        baselineSalesVelocity: 15,
        seed: 9999
    );

    expect($run1->finalPriceIdr)->toBe($run2->finalPriceIdr)
        ->and($run1->decisionHash)->toBe($run2->decisionHash)
        ->and($run1->multiplier)->toBe($run2->multiplier)
        ->and($run1->breakdown)->toBe($run2->breakdown);
});

test('enforces floor price guardrail and prevents selling below cost', function (): void {
    CoreAiPricingRule::create([
        'item_code' => 'RETAIL-MILK-1L',
        'item_name' => 'Susu Segar Pasteur 1L',
        'channel' => 'ALL',
        'base_price_idr' => 20000,
        'floor_price_idr' => 14000,
        'ceiling_price_idr' => 25000,
        'elasticity_coefficient' => -0.80,
        'max_surge_multiplier' => 1.30,
        'decay_hours_threshold' => 24,
        'is_active' => true,
    ]);

    $engine = new DynamicPricingEngineService;

    // Severe expiry approaching (1 hour left) and low demand
    $result = $engine->calculatePrice(
        itemCode: 'RETAIL-MILK-1L',
        basePriceIdr: 20000,
        currentStock: 10,
        hoursToExpiry: 1,
        spaceOccupancyRate: 0.20,
        recentSalesVelocity: 2,
        baselineSalesVelocity: 20,
        seed: 1234
    );

    expect($result->finalPriceIdr)->toBeGreaterThanOrEqual(14000)
        ->and($result->breakdown['floor_guardrail_applied'])->toBeTrue();
});

test('enforces ceiling HET guardrail and caps price during peak surge', function (): void {
    CoreAiPricingRule::create([
        'item_code' => 'SNACK-PREMIUM-BOX',
        'item_name' => 'Parcel Nastar Premium',
        'channel' => 'ALL',
        'base_price_idr' => 100000,
        'floor_price_idr' => 80000,
        'ceiling_price_idr' => 135000,
        'elasticity_coefficient' => -0.30,
        'max_surge_multiplier' => 1.80,
        'decay_hours_threshold' => 24,
        'is_active' => true,
    ]);

    $engine = new DynamicPricingEngineService;

    // Heavy occupancy (95%) and huge sales rush
    $result = $engine->calculatePrice(
        itemCode: 'SNACK-PREMIUM-BOX',
        basePriceIdr: 100000,
        currentStock: 100,
        hoursToExpiry: 120,
        spaceOccupancyRate: 0.98,
        recentSalesVelocity: 150,
        baselineSalesVelocity: 20,
        seed: 4321
    );

    expect($result->finalPriceIdr)->toBeLessThanOrEqual(135000)
        ->and($result->finalPriceIdr)->toBe(135000)
        ->and($result->breakdown['ceiling_guardrail_applied'])->toBeTrue();
});

test('persists cryptographic decision snapshots with verifiable SHA-256 hash', function (): void {
    $engine = new DynamicPricingEngineService;

    $result = $engine->calculatePrice(
        itemCode: 'BEV-ES-TEH',
        basePriceIdr: 5000,
        currentStock: 100,
        hoursToExpiry: null,
        spaceOccupancyRate: 0.50,
        seed: 777
    );

    $snapshot = CoreAiDecisionSnapshot::where('decision_code', $result->decisionCode)->first();
    expect($snapshot)->not->toBeNull()
        ->and($snapshot->model_name)->toBe('DYNAMIC_PRICING')
        ->and($snapshot->decision_hash)->toBe($result->decisionHash);

    // Verify hash integrity directly
    $recomputedHash = hash(
        'sha256',
        $snapshot->seed.json_encode($snapshot->input_snapshot).json_encode($snapshot->decision_output)
    );
    expect($recomputedHash)->toBe($snapshot->decision_hash);
});
