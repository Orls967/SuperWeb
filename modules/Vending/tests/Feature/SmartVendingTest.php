<?php

namespace Modules\Vending\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Vending\Application\Services\SmartVendingService;
use Modules\Vending\Domain\Models\VendingRestockTask;
use Modules\Vending\Domain\Models\VendingUnit;
use Tests\TestCase;

class SmartVendingTest extends TestCase
{
    use RefreshDatabase;

    protected SmartVendingService $vendingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendingService = app(SmartVendingService::class);

        LedgerAccount::create([
            'code' => 'ven:clearing:IDR',
            'name' => 'Vending Clearing',
            'asset_code' => 'IDR',
            'kind' => 'asset',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'ven:sales_revenue:IDR',
            'name' => 'Vending Sales Revenue',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_75_1_and_75_3_holt_winters_forecast_and_waste_scaledown(): void
    {
        $historical = [100, 105, 110, 115, 120, 125, 130];
        $forecast = $this->vendingService->generateDemandAndWasteForecast(
            outletCode: 'OUT-DUTA-01',
            itemCode: 'SNK-001',
            forecastDate: Carbon::parse('2026-10-10'),
            historicalSales: $historical,
            footfallFactor: 110 // footfall boost
        );

        $this->assertNotNull($forecast->id);
        $this->assertGreaterThan(100, $forecast->forecast_qty);
        $this->assertGreaterThan(5, $forecast->waste_forecast_qty);

        // Record actual demand and waste
        // Actual demand 140 -> MAPE calculation
        // Actual waste 25 (> 1.2 * waste_forecast) -> suggest scaledown
        $updated = $this->vendingService->recordActualDemandAndWaste($forecast, 140, 25);

        $this->assertEquals(140, $updated->actual_qty);
        $this->assertNotNull($updated->mape_percent);
        $this->assertTrue($updated->suggest_scaledown);
    }

    public function test_75_2_auto_po_respects_daily_plafond(): void
    {
        // 1. Within ceiling -> auto-approved
        $resAuto = $this->vendingService->generateAutoPo(
            outletCode: 'OUT-DUTA-01',
            itemCode: 'BEV-001',
            forecastQty: 200,
            currentStock: 50,
            unitCostIdr: 50_000, // 150 * 50k = 7.5jt <= 20jt plafond
            dailyPlafonIdr: 20_000_000
        );

        $this->assertEquals('AUTO_APPROVED', $resAuto['status']);
        $this->assertTrue($resAuto['auto_approved']);
        $this->assertEquals(150, $resAuto['qty_ordered']);

        // 2. Exceeds ceiling -> manual approval required
        $resManual = $this->vendingService->generateAutoPo(
            outletCode: 'OUT-DUTA-01',
            itemCode: 'BEV-EXPENSIVE',
            forecastQty: 500,
            currentStock: 0,
            unitCostIdr: 100_000, // 500 * 100k = 50jt > 20jt plafond
            dailyPlafonIdr: 20_000_000
        );

        $this->assertEquals('MANUAL_APPROVAL_REQUIRED', $resManual['status']);
        $this->assertFalse($resManual['auto_approved']);
    }

    public function test_75_4_and_75_5_vending_sale_reduces_stock_posts_ledger_and_schedules_restock(): void
    {
        $unit = VendingUnit::create([
            'unit_code' => 'VEN-HUB-01',
            'location_name' => 'Lobby Tower A',
            'status' => 'ACTIVE',
            'current_stock' => 12,
            'critical_threshold' => 10,
            'max_capacity' => 100,
        ]);

        // Sale 5 units -> remaining stock 7 (<= critical threshold 10)
        $tx = $this->vendingService->processVendingSale(
            unit: $unit,
            itemCode: 'COFFEE-CAN',
            qty: 5,
            priceIdr: 15_000,
            paymentMethod: 'BIOMETRIC_FACE',
            biometricToken: 'user_face_token_secure_xyz'
        );

        $this->assertEquals('COMPLETED', $tx->status);
        $this->assertEquals(75_000, $tx->price_idr);
        $this->assertNotNull($tx->biometric_token_hash);

        // Check unit stock updated
        $unit->refresh();
        $this->assertEquals(7, $unit->current_stock);
        $this->assertEquals(5, $unit->total_sales_count);
        $this->assertEquals(75_000, $unit->total_sales_idr);

        // Check restock task created automatically
        $restock = VendingRestockTask::where('vending_unit_id', $unit->id)->first();
        $this->assertNotNull($restock);
        $this->assertEquals(93, $restock->suggested_qty); // 100 - 7 = 93
        $this->assertEquals('SCHEDULED', $restock->status);

        // Anti-duplicate: Trigger restock check again
        $secondTask = $this->vendingService->scheduleRestockTask($unit);
        $this->assertEquals($restock->id, $secondTask->id);
        $this->assertEquals(1, VendingRestockTask::where('vending_unit_id', $unit->id)->count());

        // Check health sensor test 75.6
        $this->vendingService->reportSensorHealth($unit, [
            'coin_slot' => 'OK',
            'optical_drop' => 'JAMMED',
            'door_lock' => 'OK',
        ]);
        $unit->refresh();
        $this->assertEquals('MAINTENANCE', $unit->status);
    }
}
