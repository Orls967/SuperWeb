<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Modules\Core\Application\Services\TwelveLinesUnifiedAnalyticsService;
use Tests\TestCase;

class TwelveLinesUnifiedAnalyticsTest extends TestCase
{
    protected TwelveLinesUnifiedAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TwelveLinesUnifiedAnalyticsService::class);
    }

    public function test_dynamic_pricing_guardrails_and_contract_priority(): void
    {
        // 1. Contract price wins over dynamic pricing
        $contractResult = $this->service->computeUnifiedPrice(
            domain: 'HOTEL',
            baseCostIdr: 1000000,
            demandMultiplier: 2.5,
            floorPriceIdr: 800000,
            ceilingPriceIdr: 3000000,
            contractPriceIdr: 1200000
        );

        $this->assertEquals(1200000, $contractResult['final_price_idr']);
        $this->assertEquals('CONTRACT_PRIORITY', $contractResult['pricing_mode']);

        // 2. High demand clamps at ceiling
        $ceilingResult = $this->service->computeUnifiedPrice(
            domain: 'VENUE',
            baseCostIdr: 500000,
            demandMultiplier: 4.0, // 2,000,000
            floorPriceIdr: 400000,
            ceilingPriceIdr: 1500000
        );

        $this->assertEquals(1500000, $ceilingResult['final_price_idr']);
        $this->assertTrue($ceilingResult['clamped_at_ceiling']);

        // 3. Low demand clamps at floor
        $floorResult = $this->service->computeUnifiedPrice(
            domain: 'LOGISTICS',
            baseCostIdr: 50000,
            demandMultiplier: 0.5, // 25,000
            floorPriceIdr: 40000,
            ceilingPriceIdr: 200000
        );

        $this->assertEquals(40000, $floorResult['final_price_idr']);
        $this->assertTrue($floorResult['clamped_at_floor']);
    }

    public function test_cross_line_risk_and_fraud_quarantine(): void
    {
        // Safe transaction
        $safe = $this->service->evaluateCrossLineRisk([
            'rapid_velocity' => false,
            'extreme_amount_deviation' => false,
        ]);
        $this->assertEquals('CLEARED', $safe['status']);
        $this->assertFalse($safe['quarantine_required']);

        // High risk fraud indicators (score = 30 + 35 = 65 >= 60)
        $fraud = $this->service->evaluateCrossLineRisk([
            'rapid_velocity' => true,
            'extreme_amount_deviation' => true,
        ]);
        $this->assertEquals('QUARANTINED', $fraud['status']);
        $this->assertTrue($fraud['quarantine_required']);
        $this->assertEquals(65, $fraud['risk_score']);
    }
}
