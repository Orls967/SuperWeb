<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\FoodProcessingService;
use Tests\TestCase;

/**
 * Fase 168 — Agri-Processing, Food Commodities & Export Grade Tests
 *
 * Covers:
 *  (a) mass balance within defined tolerance
 *  (b) quarantined lot cannot ship for export
 *  (c) farmer settlement matches grade and weight
 *  (d) food:audit = 0 discrepancy
 */
class FoodProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected FoodProcessingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FoodProcessingService::class);
    }

    /**
     * (c) Farmer settlement calculation matches grade and weight.
     */
    public function test_farmer_settlement_calculation(): void
    {
        // 5,000 kg Arabica coffee beans @ Rp 35,000 / kg = Rp 175,000,000
        $lot = $this->service->intakeLot('FARMER-ACEH-GAYO', 'COFFEE_BEANS', 5000.0, 94.5, 35000.0);

        $this->assertSame('COFFEE_BEANS', $lot->commodity_name);
        $this->assertEquals(5000.00, (float) $lot->raw_intake_kg);
        $this->assertEquals(175000000.00, (float) $lot->total_settlement_idr);
        $this->assertSame('RECEIVED', $lot->status);
    }

    /**
     * (a) Mass balance invariant validation during processing.
     */
    public function test_processing_run_mass_balance_tolerance(): void
    {
        // Input: 1,000 kg. Output: 800 kg finished + 150 kg co-product + 40 kg waste = 990 kg (10 kg / 1.0% loss -> VALID)
        $validRun = $this->service->executeProcessingRun('LOT-TEST-01', 1000.0, 800.0, 150.0, 40.0);
        $this->assertTrue((bool) $validRun->mass_balance_valid);
        $this->assertEquals(1.00, (float) $validRun->mass_loss_pct);

        // Invalid run: 1,000 kg input -> 700 kg finished + 100 kg co-product + 50 kg waste = 850 kg (15% loss -> INVALID)
        $invalidRun = $this->service->executeProcessingRun('LOT-TEST-02', 1000.0, 700.0, 100.0, 50.0);
        $this->assertFalse((bool) $invalidRun->mass_balance_valid);
        $this->assertEquals(15.00, (float) $invalidRun->mass_loss_pct);
    }

    /**
     * (b) Quarantined lot cannot ship for export.
     */
    public function test_quarantined_lot_blocks_export_shipping(): void
    {
        $lot = $this->service->intakeLot('FARMER-SUMATRA-01', 'PALM_FRUIT', 10000.0, 85.0, 2500.0);

        // Failed lab toxin test -> quarantined
        $qa = $this->service->conductQaCheck($lot->lot_code, 24.0, 12.0, false);
        $this->assertTrue((bool) $qa->is_quarantined);
        $this->assertFalse((bool) $qa->is_released_for_shipping);

        // Export attempt must fail
        $this->expectException(\RuntimeException::class);
        $this->service->shipForExport($lot->lot_code);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_food_processing_audit(): void
    {
        $this->service->executeProcessingRun('LOT-CLEAN-01', 500.0, 450.0, 40.0, 10.0);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
