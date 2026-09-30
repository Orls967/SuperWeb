<?php

declare(strict_types=1);

namespace Modules\Resto\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Resto\Application\Actions\ApproveStockCountAction;
use Modules\Resto\Application\Actions\CreatePurchaseOrderAction;
use Modules\Resto\Application\Actions\CreateStockCountAction;
use Modules\Resto\Application\Actions\PaySupplierAction;
use Modules\Resto\Application\Actions\ReceiveGoodsAction;
use Modules\Resto\Application\Actions\ReceiveStockTransferAction;
use Modules\Resto\Application\Actions\ShipStockTransferAction;
use Modules\Resto\Application\Queries\PayableAgingQuery;
use Modules\Resto\database\seeders\RestoMenuSeeder;
use Modules\Resto\Domain\Enums\POStatus;
use Modules\Resto\Domain\Enums\StockCountStatus;
use Modules\Resto\Domain\Enums\TransferStatus;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\IngredientCost;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\PurchaseOrder;
use Modules\Resto\Domain\Models\Supplier;
use Tests\TestCase;

class RestoSupplyChainAndTransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Outlet $centralKitchen;

    protected Outlet $outlet;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RestoMenuSeeder::class);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->centralKitchen = Outlet::where('type', 'central_kitchen')->firstOrFail();
        $this->outlet = Outlet::where('code', 'DM-01')->firstOrFail();

        $this->supplier = Supplier::create([
            'name' => 'PT Sumber Daging Halal',
            'contact' => '08123456789',
            'terms_days' => 30,
            'is_active' => true,
            'rating' => 5,
        ]);
    }

    public function test_po_creation_partial_receipt_and_full_receipt(): void
    {
        $ingredient = Ingredient::where('sku', 'ING-DAGING-GANDIK')->firstOrFail();
        $createPO = app(CreatePurchaseOrderAction::class);
        $receiveGoods = app(ReceiveGoodsAction::class);
        $inventoryService = app(InventoryService::class);

        $stockBefore = (float) $inventoryService->availableIngredient($ingredient->id, $this->outlet->id);

        // 1. Create PO for 10 kg (10,000 grams)
        $po = $createPO->handle(
            outletId: $this->outlet->id,
            supplierId: $this->supplier->id,
            linesData: [
                [
                    'ingredient_id' => $ingredient->id,
                    'qty' => 10,
                    'unit' => 'kg',
                    'unit_price' => 120000, // Rp 120.000 / kg = Rp 120 / gram
                ],
            ],
            creator: $this->admin,
            autoSend: true
        );

        $this->assertEquals(POStatus::SENT, $po->status);
        $poLine = $po->lines->first();
        $this->assertEquals(10000, (float) $poLine->qty_base_unit);

        // 2. Receive Partial (4 kg = 4,000 grams)
        $gr1 = $receiveGoods->handle(
            po: $po,
            linesData: [
                [
                    'po_line_id' => $poLine->id,
                    'qty_received_base_unit' => 4000,
                    'unit_cost' => 120,
                ],
            ],
            receiver: $this->admin,
            quality: 'good'
        );

        $po->refresh();
        $this->assertEquals(POStatus::PARTIALLY_RECEIVED, $po->status);
        $this->assertEquals(4000, (float) $po->lines->first()->qty_received);

        $stockAfterPart = (float) $inventoryService->availableIngredient($ingredient->id, $this->outlet->id);
        $this->assertEquals($stockBefore + 4000, $stockAfterPart);

        // 3. Receive Remaining (6 kg = 6,000 grams)
        $gr2 = $receiveGoods->handle(
            po: $po,
            linesData: [
                [
                    'po_line_id' => $poLine->id,
                    'qty_received_base_unit' => 6000,
                    'unit_cost' => 120,
                ],
            ],
            receiver: $this->admin,
            quality: 'good'
        );

        $po->refresh();
        $this->assertEquals(POStatus::RECEIVED, $po->status);
        $this->assertEquals(10000, (float) $po->lines->first()->qty_received);

        $stockAfterFull = (float) $inventoryService->availableIngredient($ingredient->id, $this->outlet->id);
        $this->assertEquals($stockBefore + 10000, $stockAfterFull);

        // AP should reflect credit balance (negative in liability)
        $apAccount = LedgerAccount::where('code', "ap:supplier:{$this->supplier->id}:IDR")->first();
        $this->assertNotNull($apAccount);
        $this->assertLessThan(0, (float) $apAccount->cached_balance);
    }

    public function test_moving_average_cost_benar_setelah_tiga_harga_beli_berbeda(): void
    {
        $ingredient = Ingredient::create([
            'sku' => 'ING-TEST-MAC',
            'name' => 'Bahan Baku Uji MAC',
            'base_unit' => 'gram',
            'category' => 'protein',
            'is_perishable' => true,
            'min_stock_base_unit' => 100,
        ]);

        $receiveGoods = app(ReceiveGoodsAction::class);
        $createPO = app(CreatePurchaseOrderAction::class);

        // Batch 1: 1,000 grams @ Rp 100/gram -> Total Rp 100.000. New MAC = 100.000000
        $po1 = $createPO->handle(
            outletId: $this->outlet->id,
            supplierId: $this->supplier->id,
            linesData: [['ingredient_id' => $ingredient->id, 'qty' => 1000, 'unit' => 'gram', 'unit_price' => 100]],
            creator: $this->admin,
            autoSend: true
        );
        $receiveGoods->handle(
            po: $po1,
            linesData: [['po_line_id' => $po1->lines->first()->id, 'qty_received_base_unit' => 1000, 'unit_cost' => 100]],
            receiver: $this->admin
        );

        $cost1 = IngredientCost::where('ingredient_id', $ingredient->id)->where('outlet_id', $this->outlet->id)->firstOrFail();
        $this->assertEquals(100.0, (float) $cost1->moving_avg_cost_per_base_unit);

        // Batch 2: 1,000 grams @ Rp 150/gram -> Total stock 2,000g, Total value Rp 250.000 -> New MAC = 125.000000
        $po2 = $createPO->handle(
            outletId: $this->outlet->id,
            supplierId: $this->supplier->id,
            linesData: [['ingredient_id' => $ingredient->id, 'qty' => 1000, 'unit' => 'gram', 'unit_price' => 150]],
            creator: $this->admin,
            autoSend: true
        );
        $receiveGoods->handle(
            po: $po2,
            linesData: [['po_line_id' => $po2->lines->first()->id, 'qty_received_base_unit' => 1000, 'unit_cost' => 150]],
            receiver: $this->admin
        );

        $cost2 = IngredientCost::where('ingredient_id', $ingredient->id)->where('outlet_id', $this->outlet->id)->firstOrFail();
        $this->assertEquals(125.0, (float) $cost2->moving_avg_cost_per_base_unit);

        // Batch 3: 2,000 grams @ Rp 200/gram -> (2,000 * 125 + 2,000 * 200) / 4,000 = (250.000 + 400.000) / 4,000 = 650.000 / 4,000 = 162.500000
        $po3 = $createPO->handle(
            outletId: $this->outlet->id,
            supplierId: $this->supplier->id,
            linesData: [['ingredient_id' => $ingredient->id, 'qty' => 2000, 'unit' => 'gram', 'unit_price' => 200]],
            creator: $this->admin,
            autoSend: true
        );
        $receiveGoods->handle(
            po: $po3,
            linesData: [['po_line_id' => $po3->lines->first()->id, 'qty_received_base_unit' => 2000, 'unit_cost' => 200]],
            receiver: $this->admin
        );

        $cost3 = IngredientCost::where('ingredient_id', $ingredient->id)->where('outlet_id', $this->outlet->id)->firstOrFail();
        $this->assertEquals(162.5, (float) $cost3->moving_avg_cost_per_base_unit);
    }

    public function test_transfer_in_transit_tidak_menghilangkan_nilai_pada_neraca(): void
    {
        $ingredient = Ingredient::where('sku', 'ING-BERAS-SOLOK')->firstOrFail();
        $inventoryService = app(InventoryService::class);
        $shipAction = app(ShipStockTransferAction::class);
        $receiveAction = app(ReceiveStockTransferAction::class);

        // Ensure central kitchen has stock
        $inventoryService->addIngredient(
            ingredientId: $ingredient->id,
            outletId: $this->centralKitchen->id,
            qtyBaseUnit: '50000', // 50 kg
            reason: StockMovementReason::PURCHASE
        );

        // Initialize cost
        IngredientCost::updateOrCreate(
            ['ingredient_id' => $ingredient->id, 'outlet_id' => $this->centralKitchen->id],
            ['moving_avg_cost_per_base_unit' => '15.000000', 'last_purchase_cost' => '15.000000', 'updated_at' => now()]
        );

        $transitBalBefore = LedgerAccount::where('code', 'inventory:resto:transit:IDR')->value('cached_balance') ?? '0';

        // 1. Ship 20,000 grams from CK to Outlet
        $transfer = $shipAction->handle(
            fromOutletId: $this->centralKitchen->id,
            toOutletId: $this->outlet->id,
            lines: [
                ['ingredient_id' => $ingredient->id, 'qty' => 20000],
            ],
            shipper: $this->admin
        );

        $this->assertEquals(TransferStatus::IN_TRANSIT, $transfer->status);

        // In-transit ledger account should increase by exactly 20,000g * Rp 15 = Rp 300,000
        $transitBalMid = LedgerAccount::where('code', 'inventory:resto:transit:IDR')->firstOrFail()->cached_balance;
        $diffTransit = (float) $transitBalMid - (float) $transitBalBefore;
        $this->assertEquals(300000.0, $diffTransit);

        // 2. Receive fully at Outlet
        $receiveAction->handle(
            transfer: $transfer,
            receivedQtys: [$ingredient->id => 20000],
            receiver: $this->admin
        );

        $transfer->refresh();
        $this->assertEquals(TransferStatus::RECEIVED, $transfer->status);

        // In-transit balance should return to before
        $transitBalAfter = LedgerAccount::where('code', 'inventory:resto:transit:IDR')->firstOrFail()->cached_balance;
        $this->assertEquals((float) $transitBalBefore, (float) $transitBalAfter);

        // Outlet inventory account increased by Rp 300,000
        $destBal = LedgerAccount::where('code', "inventory:resto:{$this->outlet->code}:IDR")->firstOrFail()->cached_balance;
        $this->assertGreaterThanOrEqual(300000.0, (float) $destBal);
    }

    public function test_transfer_dengan_selisih_mencatat_discrepancy_ke_expense_waste(): void
    {
        $ingredient = Ingredient::where('sku', 'ING-TELUR-AYAM')->firstOrFail();
        $inventoryService = app(InventoryService::class);
        $shipAction = app(ShipStockTransferAction::class);
        $receiveAction = app(ReceiveStockTransferAction::class);

        $inventoryService->addIngredient(
            ingredientId: $ingredient->id,
            outletId: $this->centralKitchen->id,
            qtyBaseUnit: '100', // 100 butir
            reason: StockMovementReason::PURCHASE
        );

        IngredientCost::updateOrCreate(
            ['ingredient_id' => $ingredient->id, 'outlet_id' => $this->centralKitchen->id],
            ['moving_avg_cost_per_base_unit' => '2000.000000', 'last_purchase_cost' => '2000.000000', 'updated_at' => now()]
        );

        // Ship 50 pcs
        $transfer = $shipAction->handle(
            fromOutletId: $this->centralKitchen->id,
            toOutletId: $this->outlet->id,
            lines: [['ingredient_id' => $ingredient->id, 'qty' => 50]],
            shipper: $this->admin
        );

        $wasteBefore = (float) (LedgerAccount::where('code', 'expense:resto:waste:IDR')->value('cached_balance') ?? '0');

        // Receive only 45 pcs (5 broken in transit = Rp 10.000 waste)
        $receiveAction->handle(
            transfer: $transfer,
            receivedQtys: [$ingredient->id => 45],
            receiver: $this->admin,
            varianceNote: '5 butir pecah di perjalanan'
        );

        $transfer->refresh();
        $this->assertEquals(TransferStatus::DISCREPANCY, $transfer->status);

        $wasteAfter = (float) LedgerAccount::where('code', 'expense:resto:waste:IDR')->firstOrFail()->cached_balance;
        $this->assertEquals($wasteBefore + 10000.0, $wasteAfter);
    }

    public function test_stock_opname_dengan_selisih_minus_menyesuaikan_stok_dan_beban_waste(): void
    {
        $ingredient = Ingredient::where('sku', 'ING-SANTAN-MURNI')->firstOrFail();

        $createCount = app(CreateStockCountAction::class);
        $approveCount = app(ApproveStockCountAction::class);
        $inventoryService = app(InventoryService::class);

        // Ensure baseline stock and cost
        $inventoryService->addIngredient(
            ingredientId: $ingredient->id,
            outletId: $this->outlet->id,
            qtyBaseUnit: '10000',
            reason: StockMovementReason::PURCHASE
        );

        IngredientCost::updateOrCreate(
            ['ingredient_id' => $ingredient->id, 'outlet_id' => $this->outlet->id],
            ['moving_avg_cost_per_base_unit' => '25.000000', 'last_purchase_cost' => '25.000000', 'updated_at' => now()]
        );

        $systemStock = (float) $inventoryService->availableIngredient($ingredient->id, $this->outlet->id);
        $countedStock = max(0, $systemStock - 1000); // 1,000 units missing

        $count = $createCount->handle(
            outletId: $this->outlet->id,
            countedQuantities: [$ingredient->id => $countedStock],
            counter: $this->admin,
            notes: 'Pemeriksaan stok akhir pekan'
        );

        $this->assertEquals(StockCountStatus::SUBMITTED, $count->status);
        $line = $count->lines->where('ingredient_id', $ingredient->id)->firstOrFail();
        $this->assertEquals(-1000, (float) $line->variance);
        $this->assertLessThan(0, $line->variance_value);

        $wasteBefore = (float) (LedgerAccount::where('code', 'expense:resto:waste:IDR')->value('cached_balance') ?? '0');

        $approveCount->handle($count, $this->admin);

        $count->refresh();
        $this->assertEquals(StockCountStatus::APPROVED, $count->status);

        $stockAfter = (float) $inventoryService->availableIngredient($ingredient->id, $this->outlet->id);
        $this->assertEquals($countedStock, $stockAfter);

        $wasteAfter = (float) LedgerAccount::where('code', 'expense:resto:waste:IDR')->firstOrFail()->cached_balance;
        $this->assertGreaterThan($wasteBefore, $wasteAfter);
    }

    public function test_payable_aging_query_dan_pembayaran_supplier(): void
    {
        $createPO = app(CreatePurchaseOrderAction::class);
        $receiveGoods = app(ReceiveGoodsAction::class);
        $paySupplier = app(PaySupplierAction::class);
        $agingQuery = app(PayableAgingQuery::class);
        $ingredient = Ingredient::where('sku', 'ING-DAGING-GANDIK')->firstOrFail();

        // Create PO received 40 days ago (aging 31-60)
        $poOld = $createPO->handle(
            outletId: $this->outlet->id,
            supplierId: $this->supplier->id,
            linesData: [['ingredient_id' => $ingredient->id, 'qty' => 2, 'unit' => 'kg', 'unit_price' => 100000]],
            creator: $this->admin,
            autoSend: true
        );
        $receiveGoods->handle(
            po: $poOld,
            linesData: [['po_line_id' => $poOld->lines->first()->id, 'qty_received_base_unit' => 2000, 'unit_cost' => 100]],
            receiver: $this->admin
        );
        $poOld->update(['created_at' => now()->subDays(75), 'expected_at' => now()->subDays(70)]);

        // Create PO received 5 days ago (aging 0-30)
        $poNew = $createPO->handle(
            outletId: $this->outlet->id,
            supplierId: $this->supplier->id,
            linesData: [['ingredient_id' => $ingredient->id, 'qty' => 1, 'unit' => 'kg', 'unit_price' => 100000]],
            creator: $this->admin,
            autoSend: true
        );
        $receiveGoods->handle(
            po: $poNew,
            linesData: [['po_line_id' => $poNew->lines->first()->id, 'qty_received_base_unit' => 1000, 'unit_cost' => 100]],
            receiver: $this->admin
        );

        $aging = $agingQuery->execute();
        $this->assertGreaterThan(0, $aging['bracket_0_30']);
        $this->assertGreaterThan(0, $aging['bracket_31_60']);

        // Settle the new PO
        $paySupplier->handle(
            supplier: $this->supplier,
            amount: $poNew->grand_total,
            payerUser: $this->admin,
            po: $poNew
        );

        $poNew->refresh();
        $this->assertEquals($poNew->grand_total, $poNew->paid_amount);
    }

    public function test_monitor_stock_levels_command_membuat_draft_po(): void
    {
        $ingredient = Ingredient::where('sku', 'ING-BAWANG-MERAH')->firstOrFail();
        $ingredient->update([
            'min_stock_base_unit' => 150000, // min 150kg > available 100kg
        ]);

        $exitCode = Artisan::call('resto:check-stock');
        $this->assertEquals(0, $exitCode);

        // A draft PO should have been generated for low stock item
        $draftPo = PurchaseOrder::where('status', POStatus::DRAFT)
            ->whereHas('lines', fn ($q) => $q->where('ingredient_id', $ingredient->id))
            ->first();

        $this->assertNotNull($draftPo, 'Draft PO should be created for ingredients below min_stock.');
    }

    public function test_bank_reconcile_bersih_setelah_seluruh_alur_rantai_pasok(): void
    {
        $exitCode = Artisan::call('bank:reconcile');
        $this->assertEquals(0, $exitCode, 'Ledger must be completely reconciled with 0 discrepancy.');
    }
}
