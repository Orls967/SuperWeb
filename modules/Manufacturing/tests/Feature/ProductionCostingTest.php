<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Manufacturing\Application\Services\CostingService;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Manufacturing\Application\Services\ProductionService;
use Modules\Manufacturing\Domain\Models\CostVersion;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialBalance;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Domain\Models\OrderCost;
use Modules\Manufacturing\Domain\Models\Variance;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Events\OrderPaid;
use Modules\Store\Domain\Models\Category;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\OrderItem;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

/**
 * Regresi Fase 38: standard cost versi + four-eyes, jurnal WIP/FG/COGS,
 * varians post/capitalize, settle order closed, margin report, audit.
 */
class ProductionCostingTest extends TestCase
{
    use RefreshDatabase;

    private ProductionService $production;

    private ManufacturingService $mfg;

    private CostingService $costing;

    private User $admin;

    private Material $fg;

    private Material $raw;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->production = app(ProductionService::class);
        $this->mfg = app(ManufacturingService::class);
        $this->costing = app(CostingService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();

        $this->fg = $this->mfg->createMaterial(['code' => 'FG-COST', 'name' => 'Jadi cost', 'kind' => 'finished', 'base_uom' => 'pcs']);
        $this->raw = $this->mfg->createMaterial(['code' => 'RM-COST', 'name' => 'Bahan cost', 'kind' => 'raw', 'base_uom' => 'kg']);

        $this->mfg->createBom([
            'output_material_id' => $this->fg->id,
            'name' => 'BOM cost',
            'effective_from' => now()->toDateString(),
            'lines' => [['input_material_id' => $this->raw->id, 'qty' => 2, 'uom' => 'kg']],
        ], $this->admin);
    }

    private function seedLot(float $qty = 50, int $unitCost = 10_000): MaterialLot
    {
        $lot = MaterialLot::create([
            'material_id' => $this->raw->id, 'lot_number' => 'COST-'.uniqid(),
            'qty' => $qty, 'unit_cost_idr' => $unitCost,
            'produced_at' => now()->toDateString(), 'source_type' => 'purchase',
            'status' => 'active',
        ]);
        MaterialBalance::updateOrCreate(
            ['material_id' => $this->raw->id],
            ['qty_on_hand' => (float) MaterialBalance::where('material_id', $this->raw->id)->value('qty_on_hand') + $qty, 'qty_reserved' => 0]
        );

        return $lot;
    }

    private function ledgerBalance(string $code): int
    {
        return (int) (LedgerAccount::where('code', $code)->value('cached_balance') ?? 0);
    }

    // ── 38.1 Standard cost + approval ────────────────────────────────────

    public function test_cost_version_rolls_up_and_requires_four_eyes(): void
    {
        $version = $this->costing->createCostVersion('STD-2026', [
            $this->raw->id => 10_000,
        ], $this->admin, 'Perubahan tarif bahan');

        $this->assertSame(1, $version->version);
        $this->assertSame('draft', $version->status);

        $rawCost = $version->standardCosts->firstWhere('material_id', $this->raw->id);
        $this->assertSame(10_000, (int) $rawCost->unit_cost_idr);

        // Barang jadi di-roll-up dari bahan (2 kg × 10.000).
        $fgCost = $version->standardCosts->firstWhere('material_id', $this->fg->id);
        $this->assertSame(20_000, (int) $fgCost->unit_cost_idr, 'BOM 2 kg × 10rb = 20rb/unit.');

        $submitted = $this->costing->submitCostVersion($version, $this->admin);
        $this->assertSame('pending_approval', $submitted->status);

        // Four-eyes: creator tidak boleh menyetujui.
        try {
            $this->costing->approveCostVersion($submitted, $this->admin);
            $this->fail('Four-eyes dilanggar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Four-Eyes', $e->getMessage());
        }

        $reviewer = User::factory()->create(['role' => 'admin']);
        $approved = $this->costing->approveCostVersion($submitted, $reviewer);
        $this->assertSame('approved', $approved->status);
        $this->assertSame($approved->id, CostVersionApprovedId());
        $this->assertSame(20_000, $this->costing->standardUnitCost($this->fg->id));
    }

    // ── 38.2/38.3 Jurnal order: issue → konversi → FG ────────────────────

    public function test_production_journals_flow_wip_to_fg_and_reconcile(): void
    {
        $this->seedLot(50, 10_000);
        $order = $this->production->createProductionOrder([
            'material_id' => $this->fg->id, 'qty' => 10,
            'due_date' => now()->addDays(3)->toDateString(),
        ], $this->admin);
        $this->production->transition($order, 'released', $this->admin);

        // Issue 20 kg × 10rb = 200.000 → DR WIP.
        $this->production->issueMaterials($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 20, 'method' => 'fifo'],
        ], $this->admin);

        $this->assertSame(200_000, $this->ledgerBalance(CostingService::ACCT_WIP), 'Jurnal issue masuk WIP.');
        $this->assertSame(-200_000, $this->ledgerBalance(CostingService::ACCT_MATERIALS), 'CR material (positif 200rb, kredit = −).');
        $this->artisan('bank:reconcile')->assertSuccessful();

        // Konversi: operasi 60 menit di WC biaya/jam 0 → tidak ada jurnal nol.
        $report = $this->production->startOperation($order->fresh(), 1);
        $this->production->finishOperation($report, 10);
        $this->assertSame(200_000, $this->ledgerBalance(CostingService::ACCT_WIP), 'WC tanpa biaya → konversi 0.');

        // Transisi completed → backflush tidak menambah (sudah cukup).
        $this->production->transition($order->fresh(), 'completed', $this->admin);
        $cost = OrderCost::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(200_000, (int) $cost->total_idr);
        $this->assertSame(20_000, (int) $cost->unit_cost_idr);

        // FG receipt 10 pcs → DR FG 200rb / CR WIP 200rb.
        $this->production->receiveFg($order->fresh(), 10, $this->admin, 'FG-LOT-C1');
        $this->assertSame(0, $this->ledgerBalance(CostingService::ACCT_WIP), 'WIP terkuras penuh.');
        $this->assertSame(200_000, $this->ledgerBalance(CostingService::ACCT_FG));
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    // ── 38.4 Varians ─────────────────────────────────────────────────────

    public function test_variances_are_posted_and_idempotent(): void
    {
        // Beli di atas standar: lot 15rb vs std 10rb → price variance +.
        $this->seedLot(50, 15_000);
        $this->costing->createCostVersion('STD-V', [$this->raw->id => 10_000], $this->admin);
        $version = CostVersion::orderByDesc('version')->first();
        $this->costing->approveCostVersion($this->costing->submitCostVersion($version, $this->admin), User::factory()->create(['role' => 'admin']));

        $order = $this->production->createProductionOrder([
            'material_id' => $this->fg->id, 'qty' => 10, 'due_date' => now()->addDays(3)->toDateString(),
        ], $this->admin);
        $this->production->transition($order, 'released', $this->admin);
        $this->production->issueMaterials($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 20, 'method' => 'fifo'],
        ], $this->admin);
        $report = $this->production->startOperation($order->fresh(), 1);
        $this->production->finishOperation($report, 10);
        $this->production->transition($order->fresh(), 'completed', $this->admin);

        $price = Variance::where('order_id', $order->id)->where('kind', 'price')->firstOrFail();
        // 20 kg × (15.000 − 10.000) = +100.000 unfavourable.
        $this->assertSame(100_000, (int) $price->amount_idr);
        $this->assertTrue($price->posted);
        $this->assertSame(
            100_000,
            $this->ledgerBalance(CostingService::ACCT_VARIANCE),
            'Varians post → DR beban varians.'
        );

        // Idempoten: run ulang tidak menggandakan jurnal.
        $before = $this->ledgerBalance(CostingService::ACCT_VARIANCE);
        $this->costing->postVariances($order->fresh());
        $this->assertSame($before, $this->ledgerBalance(CostingService::ACCT_VARIANCE));
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    // ── 38.8 Settle order closed ─────────────────────────────────────────

    public function test_closed_order_has_zero_remaining_wip(): void
    {
        $this->seedLot(50, 10_000);
        $order = $this->production->createProductionOrder([
            'material_id' => $this->fg->id, 'qty' => 10, 'due_date' => now()->addDays(3)->toDateString(),
        ], $this->admin);
        $this->production->transition($order, 'released', $this->admin);
        $this->production->issueMaterials($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 20, 'method' => 'fifo'],
        ], $this->admin);
        $report = $this->production->startOperation($order->fresh(), 1);
        $this->production->finishOperation($report, 10);
        $this->production->transition($order->fresh(), 'completed', $this->admin);

        // Terima sebagian (5 dari 10) → setengah WIP tertahan.
        $this->production->receiveFg($order->fresh(), 5, $this->admin);
        $this->assertSame(100_000, $this->ledgerBalance(CostingService::ACCT_WIP));

        $this->production->transition($order->fresh(), 'closed', $this->admin);

        // Settle memindahkan sisa WIP → FG.
        $this->assertSame(0, $this->ledgerBalance(CostingService::ACCT_WIP), 'Order closed tidak menyisakan WIP.');
        $this->artisan('mfg:audit-costing')->assertSuccessful();
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    // ── 38.5 COGS via event Store ────────────────────────────────────────

    public function test_order_paid_event_posts_cogs_idempotently(): void
    {
        $this->seedLot(50, 10_000);

        $customer = User::factory()->create(['role' => 'customer']);
        $category = Category::create(['name' => 'Pabrik', 'slug' => 'pabrik']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Jadi cost', 'slug' => 'jadi-cost-cogs',
            'sku' => 'FG-COST', 'price' => 50_000, 'cached_stock' => 10, 'is_car' => false,
        ]);
        $order = Order::create([
            'uuid' => (string) Str::uuid(), 'number' => 'ORD-COGS-1',
            'user_id' => $customer->id, 'status' => OrderStatus::PAID,
            'subtotal' => 50_000, 'shipping_fee' => 0, 'discount' => 0, 'grand_total' => 50_000,
            'shipping_address' => ['street' => 'Jl. Uji', 'city' => 'Banjarmasin'],
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'name_snapshot' => 'Jadi cost', 'price_snapshot' => 50_000, 'qty' => 1, 'line_total' => 50_000,
        ]);

        event(new OrderPaid($order->id, $customer->id, 50_000));

        // HPP = FIFO 1 lot × 10rb (standard unit cost bahan kosong → 0 utk FG,
        // lot FG belum ada → pakai 0; buat lot FG agar HPP nyata).
        // Lot FG dihasilkan penerimaan produksi.
        MaterialLot::create([
            'material_id' => $this->fg->id, 'lot_number' => 'FG-SALE',
            'qty' => 5, 'unit_cost_idr' => 20_000, 'produced_at' => now()->toDateString(),
            'source_type' => 'production', 'status' => 'active',
        ]);

        event(new OrderPaid($order->id, $customer->id, 50_000)); // ulang → idempoten

        $cogs = $this->ledgerBalance(CostingService::ACCT_COGS);
        $this->assertSame(20_000, $cogs, 'HPP = 1 unit × 20.000 (FIFO lot FG).');
        $this->assertSame(-20_000, $this->ledgerBalance(CostingService::ACCT_FG), 'CR FG.');
        $this->artisan('bank:reconcile')->assertSuccessful();
        unset($item);
    }

    // ── Route render ─────────────────────────────────────────────────────

    public function test_costing_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('manufacturing.costing.index'))
            ->assertOk()
            ->assertSee('Biaya Produksi');
    }

    // ── 38.7 Laporan margin + 38.8 audit ─────────────────────────────────

    public function test_margin_report_and_audit_command(): void
    {
        $this->seedLot(50, 10_000);
        $order = $this->production->createProductionOrder([
            'material_id' => $this->fg->id, 'qty' => 10, 'due_date' => now()->addDays(3)->toDateString(),
        ], $this->admin);
        $this->production->transition($order, 'released', $this->admin);
        $this->production->issueMaterials($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 20, 'method' => 'fifo'],
        ], $this->admin);
        $report = $this->production->startOperation($order->fresh(), 1);
        $this->production->finishOperation($report, 10);
        $this->production->transition($order->fresh(), 'completed', $this->admin);
        $this->production->receiveFg($order->fresh(), 10, $this->admin);

        $rows = $this->costing->marginReport();
        $fgRow = collect($rows)->firstWhere('code', 'FG-COST');
        $this->assertNotNull($fgRow, 'Barang jadi muncul di laporan margin.');
        $this->assertSame(20_000, $fgRow['unit_cost_idr']);

        $this->artisan('mfg:audit-costing')->assertSuccessful();
        $this->artisan('bank:reconcile')->assertSuccessful();
    }
}

/** Helper: id versi biaya approved terakhir. */
function CostVersionApprovedId(): ?string
{
    return CostVersion::approved()?->getKey();
}
