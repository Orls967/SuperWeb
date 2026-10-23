<?php

declare(strict_types=1);

namespace Modules\Manufacturing\tests\Feature\C2m;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Manufacturing\Application\Services\C2m\VmiAndC2mService;
use Modules\Manufacturing\Domain\Models\C2m\C2mCustomOrder;
use Modules\Manufacturing\Domain\Models\C2m\VmiReplenishment;
use Tests\TestCase;

class VmiAndC2mManufacturingTest extends TestCase
{
    use RefreshDatabase;

    protected VmiAndC2mService $service;

    protected User $admin;

    protected User $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->service = app(VmiAndC2mService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->planner = User::where('role', 'planner')->first()
            ?? User::factory()->create(['role' => 'planner']);
    }

    public function test_82_1_vmi_reorder_point_triggers_auto_po_and_commits_budget(): void
    {
        // 1. Stock 15 <= reorder point 20 -> auto ordered (within plafond)
        $vmi1 = $this->service->evaluateVmiTrigger(
            supplierId: 501,
            sku: 'FASTENER-M8-TITANIUM',
            currentStock: 15,
            reorderPoint: 20,
            reorderQty: 100,
            unitCostIdr: 50_000, // 5,000,000 IDR <= 50,000,000 IDR plafond
            contractPlafondIdr: 50_000_000
        );

        $this->assertNotNull($vmi1);
        $this->assertEquals('AUTO_ORDERED', $vmi1->status);
        $this->assertEquals(5_000_000, $vmi1->total_estimated_idr);

        // Check budget commitment in ledger
        $comm = LedgerAccount::where('code', 'mfg:vmi_commitment:IDR')->first();
        $enc = LedgerAccount::where('code', 'mfg:vmi_budget_encumbrance:IDR')->first();
        $this->assertEquals('5000000', (string) $comm->cached_balance);
        $this->assertEquals('-5000000', (string) $enc->cached_balance);

        // 2. Duplicate trigger on same day is idempotent
        $vmi2 = $this->service->evaluateVmiTrigger(
            supplierId: 501,
            sku: 'FASTENER-M8-TITANIUM',
            currentStock: 12,
            reorderPoint: 20,
            reorderQty: 100,
            unitCostIdr: 50_000,
            contractPlafondIdr: 50_000_000
        );
        $this->assertEquals($vmi1->id, $vmi2->id);
        $this->assertEquals(1, VmiReplenishment::where('sku', 'FASTENER-M8-TITANIUM')->count());

        // 3. Stock above reorder point does not trigger
        $vmiNone = $this->service->evaluateVmiTrigger(
            supplierId: 501,
            sku: 'OTHER-PART',
            currentStock: 50,
            reorderPoint: 20,
            reorderQty: 100,
            unitCostIdr: 50_000
        );
        $this->assertNull($vmiNone);

        // 4. Exceeding contract plafond requires approval
        $vmiHigh = $this->service->evaluateVmiTrigger(
            supplierId: 502,
            sku: 'EXPENSIVE-TURBINE-BLADE',
            currentStock: 5,
            reorderPoint: 10,
            reorderQty: 20,
            unitCostIdr: 5_000_000, // 100,000,000 IDR > 50jt plafond
            contractPlafondIdr: 50_000_000
        );
        $this->assertEquals('PENDING_APPROVAL', $vmiHigh->status);
    }

    public function test_82_1_vmi_approval_posts_ledger_commitment(): void
    {
        $vmi = $this->service->evaluateVmiTrigger(
            supplierId: 601,
            sku: 'HEAVY-ENGINE-BLOCK',
            currentStock: 2,
            reorderPoint: 5,
            reorderQty: 10,
            unitCostIdr: 10_000_000, // 100,000,000 IDR
            contractPlafondIdr: 40_000_000
        );
        $this->assertEquals('PENDING_APPROVAL', $vmi->status);

        $approved = $this->service->approveVmiReplenishment($vmi);
        $this->assertEquals('APPROVED', $approved->status);

        // Verify ledger commitment posted
        $comm = LedgerAccount::where('code', 'mfg:vmi_commitment:IDR')->first();
        $this->assertEquals('100000000', (string) $comm->cached_balance);
    }

    public function test_82_3_c2m_bom_cycle_detection_blocks_circular_dependency(): void
    {
        // Node A -> Node B -> Node C -> Node A (cycle)
        $cyclicHierarchy = [
            ['code' => 'NODE-A', 'parent' => null, 'name' => 'Assembly A', 'material' => 'ALU', 'qty' => 1, 'unit_cost_idr' => 10000],
            ['code' => 'NODE-B', 'parent' => 'NODE-A', 'name' => 'Sub-Assembly B', 'material' => 'STEEL', 'qty' => 1, 'unit_cost_idr' => 20000],
            ['code' => 'NODE-C', 'parent' => 'NODE-B', 'name' => 'Component C', 'material' => 'CF', 'qty' => 1, 'unit_cost_idr' => 30000],
            ['code' => 'NODE-A', 'parent' => 'NODE-C', 'name' => 'Assembly A loop', 'material' => 'ALU', 'qty' => 1, 'unit_cost_idr' => 10000],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $this->service->configureAndQuoteC2m(
            userId: (int) $this->admin->id,
            baseModel: 'CYCLIC-PART',
            parameters: ['size_mm' => 500],
            bomItemsCost: ['STEEL' => 50000],
            bomHierarchy: $cyclicHierarchy
        );
    }

    public function test_82_3_and_82_4_c2m_configurator_bom_cost_roll_up_and_spk_routing(): void
    {
        $bomCosts = [
            'RAW_ALUMINUM_7075' => 1_500_000,
            'CNC_TOOLING_WEAR' => 300_000,
            'HARDWARE_BOLTS' => 200_000,
        ]; // Base BOM = 2,000,000 IDR

        $validHierarchy = [
            ['code' => 'ROOT-ARM', 'parent' => null, 'name' => 'Control Arm Body', 'material' => 'RAW_ALUMINUM_7075', 'qty' => 1, 'unit_cost_idr' => 1500000],
            ['code' => 'BUSHING-01', 'parent' => 'ROOT-ARM', 'name' => 'Urethane Bushing', 'material' => 'HARDWARE_BOLTS', 'qty' => 2, 'unit_cost_idr' => 100000],
        ];

        $customOrder = $this->service->configureAndQuoteC2m(
            userId: (int) $this->admin->id,
            baseModel: 'CUSTOM_CONTROL_ARM',
            parameters: [
                'size_mm' => 350,
                'finish' => 'TITANIUM_COATED', // +35% complexity
                'tolerance' => 'AEROSPACE_PRECISION', // +25% complexity
            ],
            bomItemsCost: $bomCosts,
            bomHierarchy: $validHierarchy
        );

        // Total complexity = 1.0 + 0.35 + 0.25 = 1.60
        $this->assertEquals(1.60, (float) $customOrder->complexity_factor);
        // Rolled up BOM = 2,000,000 * 1.60 = 3,200,000 IDR
        $this->assertEquals(3_200_000, $customOrder->rolled_up_bom_cost_idr);
        // Final price = 3,200,000 * 1.30 = 4,160,000 IDR
        $this->assertEquals(4_160_000, $customOrder->final_price_idr);
        $this->assertEquals('DESIGN_VALIDATED', $customOrder->status);
        $this->assertEquals(2, $customOrder->bomNodes()->count());

        // Issue factory SPK
        $issued = $this->service->issueFactorySpk($customOrder);
        $this->assertEquals('SPK_ISSUED', $issued->status);
        $this->assertNotNull($issued->spk_number);

        // 82.5 Cancel after SPK issued incurs material penalty
        $cancelled = $this->service->cancelC2mOrder($issued);
        $this->assertEquals('CANCELLED_WITH_MATERIAL_PENALTY', $cancelled->status);
    }

    public function test_82_6_vmi_and_c2m_summaries(): void
    {
        $this->service->evaluateVmiTrigger(
            supplierId: 701,
            sku: 'PART-SUMMARY-01',
            currentStock: 4,
            reorderPoint: 10,
            reorderQty: 20,
            unitCostIdr: 100000
        );

        $vmiSummary = $this->service->getVmiDashboardSummary();
        $this->assertGreaterThanOrEqual(1, $vmiSummary['total_replenishments']);
        $this->assertGreaterThanOrEqual(1, $vmiSummary['auto_ordered_count']);

        $c2mBoard = $this->service->getC2mProductionBoard();
        $this->assertArrayHasKey('pipeline_value_idr', $c2mBoard);
        $this->assertArrayHasKey('by_status', $c2mBoard);
    }

    public function test_82_7_vmi_audit_command_enforces_zero_discrepancy(): void
    {
        $this->artisan('vmi:audit')->assertSuccessful();

        // Introduce discrepancy: auto-ordered exceeding contract plafond
        $badVmi = VmiReplenishment::create([
            'vmi_code' => 'VMI-BAD-01',
            'supplier_id' => 999,
            'sku' => 'SKU-BAD-VMI',
            'current_shelf_stock' => 1,
            'reorder_point' => 5,
            'suggested_reorder_qty' => 50,
            'total_estimated_idr' => 80_000_000,
            'contract_plafond_idr' => 20_000_000, // violation: estimated > plafond with AUTO_ORDERED
            'status' => 'AUTO_ORDERED',
            'idempotency_key' => 'VMI:999:BAD:2026-10-19',
        ]);

        $this->artisan('vmi:audit')
            ->assertFailed()
            ->expectsOutputToContain('vmi.auto_order_over_plafond');

        $badVmi->delete();
        $this->artisan('vmi:audit')->assertSuccessful();
    }

    public function test_82_http_endpoints_and_rbac(): void
    {
        // 1. Access dashboard
        $res = $this->actingAs($this->admin)->get(route('manufacturing.vmi-c2m.index'));
        $res->assertOk();
        $res->assertViewIs('manufacturing::vmi_c2m');

        // 2. Trigger VMI via POST
        $postVmi = $this->actingAs($this->planner)->post(route('manufacturing.vmi.trigger'), [
            'supplier_id' => 801,
            'sku' => 'SKU-HTTP-01',
            'current_stock' => 5,
            'reorder_point' => 10,
            'reorder_qty' => 30,
            'unit_cost_idr' => 150000,
            'contract_plafond_idr' => 20000000,
        ]);
        $postVmi->assertRedirect();
        $this->assertDatabaseHas('mfg_vmi_replenishments', ['sku' => 'SKU-HTTP-01']);

        // 3. Store C2M Order via POST
        $postC2m = $this->actingAs($this->planner)->post(route('manufacturing.c2m.orders.store'), [
            'base_model' => 'CUSTOM_DIFFUSER',
            'size_mm' => 850,
            'material' => 'CARBON_FIBER',
            'finish' => 'STANDARD',
            'raw_material_cost_idr' => 1200000,
        ]);
        $postC2m->assertRedirect();
        $order = C2mCustomOrder::where('base_model', 'CUSTOM_DIFFUSER')->first();
        $this->assertNotNull($order);

        // 4. Issue SPK via POST
        $postSpk = $this->actingAs($this->planner)->post(route('manufacturing.c2m.orders.spk', $order));
        $postSpk->assertRedirect();
        $this->assertEquals('SPK_ISSUED', $order->fresh()->status);

        // 5. Cancel order via POST
        $postCancel = $this->actingAs($this->planner)->post(route('manufacturing.c2m.orders.cancel', $order));
        $postCancel->assertRedirect();
        $this->assertEquals('CANCELLED_WITH_MATERIAL_PENALTY', $order->fresh()->status);
    }
}
