<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\Ai\StockPrescriptionEngineService;
use Modules\Core\Domain\Models\CoreAiStockRecommendation;

uses(RefreshDatabase::class);

test('calculates adaptive safety stock and ROP higher during peak holiday seasons', function (): void {
    $engine = new StockPrescriptionEngineService;

    // Regular Season
    $regular = $engine->generateRecommendation(
        itemCode: 'SKU-BERAS-5KG',
        itemName: 'Beras Pandan Wangi 5kg',
        currentStock: 100,
        avgDailyDemand: 20,
        leadTimeDays: 4,
        unitCostIdr: 70000,
        seasonCode: 'REGULAR',
        seed: 301
    );

    // Ramadan / Lebaran Season
    $ramadan = $engine->generateRecommendation(
        itemCode: 'SKU-BERAS-5KG',
        itemName: 'Beras Pandan Wangi 5kg',
        currentStock: 100,
        avgDailyDemand: 20,
        leadTimeDays: 4,
        unitCostIdr: 70000,
        seasonCode: 'RAMADAN_LEBARAN',
        seed: 301
    );

    expect($ramadan->seasonalFactor)->toBe(1.40)
        ->and($regular->seasonalFactor)->toBe(1.00)
        ->and($ramadan->safetyStock)->toBeGreaterThan($regular->safetyStock)
        ->and($ramadan->calculatedRop)->toBeGreaterThan($regular->calculatedRop);
});

test('flags human approval required when total PO cost exceeds autonomous ceiling of 10 million IDR', function (): void {
    $engine = new StockPrescriptionEngineService;

    // High unit cost item needing reorder: 50 units * Rp 500.000 = Rp 25.000.000 (> Rp 10.000.000)
    $result = $engine->generateRecommendation(
        itemCode: 'PART-ENGINE-OIL-DRUM',
        itemName: 'Oli Mesin Heavy Duty Drum 200L',
        currentStock: 2,
        avgDailyDemand: 5,
        leadTimeDays: 7,
        unitCostIdr: 500000,
        seasonCode: 'REGULAR',
        seed: 401
    );

    expect($result->requiresHumanApproval)->toBeTrue()
        ->and($result->estimatedCostIdr)->toBeGreaterThan(10_000_000);

    $rec = CoreAiStockRecommendation::where('item_code', 'PART-ENGINE-OIL-DRUM')->first();
    expect($rec)->not->toBeNull()
        ->and($rec->requires_human_approval)->toBeTrue()
        ->and($rec->status)->toBe('PROPOSED');
});

test('operates without human approval when order cost is below 10 million IDR', function (): void {
    $engine = new StockPrescriptionEngineService;

    // Inexpensive item: 50 units * Rp 15.000 = Rp 750.000 (< Rp 10.000.000)
    $result = $engine->generateRecommendation(
        itemCode: 'STATIONERY-PEN-BOX',
        itemName: 'Kotak Bolpoint Gel Hitam',
        currentStock: 5,
        avgDailyDemand: 10,
        leadTimeDays: 3,
        unitCostIdr: 15000,
        seasonCode: 'REGULAR',
        seed: 501
    );

    expect($result->requiresHumanApproval)->toBeFalse()
        ->and($result->estimatedCostIdr)->toBeLessThanOrEqual(10_000_000);
});
