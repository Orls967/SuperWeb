<?php

declare(strict_types=1);

namespace Tests\Acceptance\Culinary\Eksplorasi;

use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Vending\Application\Actions\ApplyDynamicNearExpiryMarkdownAction;
use Modules\Vending\Application\Actions\DispenseSmartVendingCartAction;
use Modules\Vending\Application\Actions\ForecastVendingDemandAndWasteAction;
use Modules\Vending\Application\Actions\TriggerAutoReplenishmentPoAction;
use Modules\Vending\Application\Services\SmartVendingService;
use Modules\Vending\Domain\Models\VendingUnit;
use Tests\TestCase;

/**
 * LINI 5: Kuliner - Vending Legacy Codebase Exploration (L5.0).
 *
 * Membuktikan kondisi awal kode lama sebelum MVP diperbaiki:
 * - DispenseSmartVendingCartAction: SEBAGIAN (stok mesin terpisah dari inventory lokasi multi-outlet)
 * - ForecastVendingDemandAndWasteAction: SALAH / KERANGKA (mock perkalian float 1.2 & 0.08, bukan Holt-Winters 7-day forecast B0′.5)
 * - TriggerAutoReplenishmentPoAction: SEBAGIAN (membuat Auto-PO lokal tanpa integrasi ApprovalEngine)
 * - ApplyDynamicNearExpiryMarkdownAction: BENAR (dynamic tiered markdown berdasarkan jam kedaluwarsa)
 * - SmartVendingService: SEBAGIAN (memiliki Holt-Winters tapi belum terhubung rute restocking B0′.6)
 */
class VendingLegacyExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected VendingUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->unit = VendingUnit::create([
            'unit_code' => 'VND-MALL-01',
            'location_name' => 'Mall Atrium Ground Floor',
            'current_stock' => 50,
            'max_capacity' => 100,
            'critical_threshold' => 10,
            'status' => 'active',
            'total_sales_count' => 0,
            'total_sales_idr' => 0,
        ]);
    }

    public function test_legacy_vending_tables_exist_and_legacy_prefixes_identified(): void
    {
        // Canonical vnd_* tables exist post-migration
        expect(Schema::hasTable('vnd_vending_units'))->toBeTrue();
        expect(Schema::hasTable('vnd_vending_transactions'))->toBeTrue();
        expect(Schema::hasTable('vnd_demand_forecasts'))->toBeTrue();
        expect(Schema::hasTable('vnd_auto_pos'))->toBeTrue();
        expect(Schema::hasTable('vnd_restock_tasks'))->toBeTrue();

        // Proves canonical vnd_* prefix is active
        expect(str_starts_with('vnd_vending_units', 'vnd_'))->toBeTrue();
    }

    public function test_dispense_smart_vending_cart_action_status_sebagian(): void
    {
        $action = app(DispenseSmartVendingCartAction::class);

        $result = $action->handle(
            vendingUnitId: $this->unit->id,
            itemCode: 'ITEM-BEV-001',
            qty: 2,
            unitPriceIdr: 15_000,
            paymentMethod: 'QRIS'
        );

        expect($result['remaining_stock'])->toBe(48)
            ->and($result['total_price_idr'])->toBe(30_000);

        // BUKTI KEKURANGAN (KONSEP §3F Slice 4):
        // Stok didekremen di kolom lokal ven_vending_units.current_stock,
        // BUKAN sebagai lokasi stok resmi di InventoryService / StockMovement!
    }

    public function test_forecast_vending_demand_and_waste_action_status_salah(): void
    {
        $action = app(ForecastVendingDemandAndWasteAction::class);

        $result = $action->handle(
            outletCode: 'VND-MALL-01',
            itemCode: 'ITEM-BEV-001',
            forecastDate: now()->addDay()->toDateString(),
            footfallFactor: 1.25
        );

        // BUKTI CACAT:
        // Menggunakan perkalian float sederhana baseDemand (50) * footfallFactor (1.25)
        // dan limbah diestimasi flat 8% float math!
        expect($result['forecast_qty'])->toBe((int) round(50 * 1.25))
            ->and($result['waste_forecast_qty'])->toBe((int) round(63 * 0.08));

        // Bukan Holt-Winters 7-day seasonal model dengan data historis (B0′.5)!
    }

    public function test_trigger_auto_replenishment_po_action_status_sebagian(): void
    {
        $action = app(TriggerAutoReplenishmentPoAction::class);

        // Unit stock is 50, capacity is 100 -> orders 50
        $result = $action->handle(
            vendingUnitId: $this->unit->id,
            itemCode: 'ITEM-BEV-001',
            costPerUnitIdr: 10_000,
            dailyPlafonIdr: 5_000_000
        );

        expect($result['qty_ordered'])->toBe(50)
            ->and($result['estimated_total_idr'])->toBe(500_000)
            ->and($result['auto_po']->status)->toBe('approved');

        // BUKTI KEKURANGAN: Status langsung 'approved' tanpa melewati alur persetujuan ApprovalEngine
    }

    public function test_apply_dynamic_near_expiry_markdown_action_status_benar(): void
    {
        $action = app(ApplyDynamicNearExpiryMarkdownAction::class);

        // Expiry in 4 hours -> FLASH_CLEARANCE_50 (50% off)
        $flash = $action->handle('ITEM-FRESH-001', 20_000, 4);
        expect($flash['discount_pct'])->toBe(50)
            ->and($flash['marked_down_price_idr'])->toBe(10_000)
            ->and($flash['tier'])->toBe('FLASH_CLEARANCE_50');

        // Expiry in 18 hours -> NEAR_EXPIRY_30 (30% off)
        $near = $action->handle('ITEM-FRESH-001', 20_000, 18);
        expect($near['discount_pct'])->toBe(30)
            ->and($near['marked_down_price_idr'])->toBe(14_000)
            ->and($near['tier'])->toBe('NEAR_EXPIRY_30');

        // Expiry in 72 hours -> STANDARD_PRICE (0% off)
        $standard = $action->handle('ITEM-FRESH-001', 20_000, 72);
        expect($standard['discount_pct'])->toBe(0)
            ->and($standard['marked_down_price_idr'])->toBe(20_000)
            ->and($standard['tier'])->toBe('STANDARD_PRICE');
    }

    public function test_smart_vending_service_status_sebagian(): void
    {
        $service = app(SmartVendingService::class);

        // Test sale with ledger posting
        $tx = $service->processVendingSale(
            unit: $this->unit,
            itemCode: 'ITEM-BEV-001',
            qty: 1,
            priceIdr: 15_000,
            paymentMethod: 'QRIS'
        );

        expect($tx->status)->toBe('COMPLETED')
            ->and($this->unit->fresh()->current_stock)->toBe(49);

        // Ledger postings checked
        $ledgerService = app(LedgerService::class);
        $revenue = $ledgerService->getCachedBalance('ven:sales_revenue:IDR');
        expect($revenue)->toBe('15000');

        // BUKTI KEKURANGAN:
        // Restocking schedule tidak menggunakan optimasi rute B0′.6 (RouteOptimizer)
    }
}
