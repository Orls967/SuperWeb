<?php

declare(strict_types=1);

namespace Modules\Vending\tests\Feature;

use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Vending\Application\Services\SmartVendingService;
use Modules\Vending\Domain\Models\VendingAutoPo;
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
        $this->seed(LedgerAccountsSeeder::class);
        $this->vendingService = app(SmartVendingService::class);
    }

    /**
     * DoR 75.7a: Forecast MAPE masuk toleransi pada seed (MAPE <= 15%).
     */
    public function test_forecast_mape_within_tolerance_with_seed(): void
    {
        // Seeded deterministic historical sales: steady upward trend
        $historical = [100, 105, 110, 115, 120, 125, 130];
        $forecastDate = Carbon::parse('2026-10-10');

        $forecast = $this->vendingService->generateDemandAndWasteForecast(
            outletCode: 'OUT-DUTA-01',
            itemCode: 'SNK-001',
            forecastDate: $forecastDate,
            historicalSales: $historical,
            footfallFactor: 100
        );

        $this->assertNotNull($forecast->id);
        $this->assertSame(134, $forecast->forecast_qty);
        $this->assertSame(11, $forecast->waste_forecast_qty);

        // Actual demand is close to prediction (e.g. 138), actual waste 12
        $updated = $this->vendingService->recordActualDemandAndWaste($forecast, 138, 12);

        $this->assertSame(138, $updated->actual_qty);
        // Error = |134 - 138| / 138 = 4 / 138 = 2.90% <= 15%
        $this->assertLessThanOrEqual(15.0, (float) $updated->mape_percent);
        $this->assertFalse($updated->suggest_scaledown);

        // If actual waste spikes > 1.2 * waste_forecast (e.g. 20 > 13.2) -> scale-down suggested
        $updatedWaste = $this->vendingService->recordActualDemandAndWaste($forecast, 138, 20);
        $this->assertTrue($updatedWaste->suggest_scaledown);
    }

    /**
     * DoR 75.7b: Auto-PO idempoten & plafon dihormati (<= 15M AUTO_APPROVED, > 15M MANUAL_APPROVAL).
     */
    public function test_auto_po_idempotent_and_ceiling_respected(): void
    {
        // 1. Within ceiling (100 * 50k = 5,000,000 IDR <= 15M plafon)
        $resAuto = $this->vendingService->generateAutoPo(
            outletCode: 'OUT-DUTA-01',
            itemCode: 'BEV-001',
            forecastQty: 150,
            currentStock: 50,
            unitCostIdr: 50_000,
            dailyPlafonIdr: 15_000_000
        );

        $this->assertSame('AUTO_APPROVED', $resAuto['status']);
        $this->assertTrue($resAuto['auto_approved']);
        $this->assertSame(100, $resAuto['qty_ordered']);
        $this->assertSame(5000000, $resAuto['total_idr']);

        // Idempotency: re-running with same parameters does not create duplicate PO
        $countBefore = VendingAutoPo::count();
        $resAutoRepeat = $this->vendingService->generateAutoPo(
            outletCode: 'OUT-DUTA-01',
            itemCode: 'BEV-001',
            forecastQty: 150,
            currentStock: 50,
            unitCostIdr: 50_000,
            dailyPlafonIdr: 15_000_000
        );
        $this->assertSame($countBefore, VendingAutoPo::count());
        $this->assertSame($resAuto['auto_po_code'], $resAutoRepeat['auto_po_code']);

        // 2. Exceeds ceiling (200 * 100k = 20,000,000 IDR > 15M plafon) -> MANUAL_APPROVAL_REQUIRED
        $resManual = $this->vendingService->generateAutoPo(
            outletCode: 'OUT-DUTA-01',
            itemCode: 'BEV-PREMIUM',
            forecastQty: 250,
            currentStock: 50,
            unitCostIdr: 100_000,
            dailyPlafonIdr: 15_000_000
        );

        $this->assertSame('MANUAL_APPROVAL_REQUIRED', $resManual['status']);
        $this->assertFalse($resManual['auto_approved']);
        $this->assertSame(20000000, $resManual['total_idr']);
    }

    /**
     * DoR 75.7c: Vending sale = stok terpotong & double-entry ledger diposting.
     */
    public function test_vending_sale_deducts_stock_and_posts_ledger(): void
    {
        $unit = VendingUnit::create([
            'unit_code' => 'VEN-HUB-01',
            'location_name' => 'Lobby Tower A',
            'status' => 'ACTIVE',
            'current_stock' => 20,
            'critical_threshold' => 10,
            'max_capacity' => 100,
        ]);

        $tx = $this->vendingService->processVendingSale(
            unit: $unit,
            itemCode: 'COFFEE-CAN',
            qty: 4,
            priceIdr: 15_000,
            paymentMethod: 'BIOMETRIC_FACE',
            biometricToken: 'user_biometric_face_token_secure'
        );

        $this->assertSame('COMPLETED', $tx->status);
        $this->assertSame(60000, $tx->price_idr);
        $this->assertNotNull($tx->biometric_token_hash);

        // Verifikasi stok unit berkurang tepat 4
        $unit->refresh();
        $this->assertSame(16, $unit->current_stock);
        $this->assertSame(4, $unit->total_sales_count);
        $this->assertSame(60000, $unit->total_sales_idr);

        // Verifikasi transaksi ledger diposting
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'VENDING_SALE',
            'reference_type' => 'VENDING_SALE',
            'reference_id' => (string) $tx->id,
        ]);
    }

    /**
     * DoR 75.7d: Restock task tidak ganda saat stok mencapai ambang kritis.
     */
    public function test_restock_task_not_duplicated(): void
    {
        $unit = VendingUnit::create([
            'unit_code' => 'VEN-HUB-02',
            'location_name' => 'Food Court Floor 2',
            'status' => 'ACTIVE',
            'current_stock' => 12,
            'critical_threshold' => 10,
            'max_capacity' => 100,
        ]);

        // Sale 4 item -> sisa stok 8 (<= 10 ambang kritis)
        $this->vendingService->processVendingSale($unit, 'SNACK-01', 4, 10000);

        $unit->refresh();
        $this->assertSame(8, $unit->current_stock);

        // Restock task otomatis dibuat
        $task = VendingRestockTask::where('vending_unit_id', $unit->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('SCHEDULED', $task->status);
        $this->assertSame(92, $task->suggested_qty); // 100 - 8 = 92

        // Penjualan berikutnya saat task sudah ada -> tidak membuat task ganda
        $this->vendingService->processVendingSale($unit, 'SNACK-01', 2, 10000);
        $taskCount = VendingRestockTask::where('vending_unit_id', $unit->id)->count();
        $this->assertSame(1, $taskCount);
    }

    /**
     * DoR 75.7e: Reconcile vending = ledger + inventory.
     */
    public function test_reconcile_vending_equals_ledger_and_inventory(): void
    {
        $unit = VendingUnit::create([
            'unit_code' => 'VEN-HUB-03',
            'location_name' => 'Basement Parking Kiosk',
            'status' => 'ACTIVE',
            'current_stock' => 50,
            'critical_threshold' => 10,
            'max_capacity' => 100,
        ]);

        $this->vendingService->processVendingSale($unit, 'WATER-500ML', 5, 5000);
        $this->vendingService->processVendingSale($unit, 'SNACK-CHIP', 2, 12500);

        $unit->refresh();
        $recon = $this->vendingService->reconcileVending($unit);

        $this->assertTrue($recon['is_reconciled']);
        $this->assertSame(50000, $recon['unit_sales_idr']); // 5*5000 + 2*12500 = 50,000
        $this->assertSame(50000, $recon['transaction_sum_idr']);

        // Injeksi diskrepansi: manipulasi omzet unit
        $unit->total_sales_idr += 10000;
        $unit->save();

        $reconCorrupted = $this->vendingService->reconcileVending($unit);
        $this->assertFalse($reconCorrupted['is_reconciled']);
    }
}
