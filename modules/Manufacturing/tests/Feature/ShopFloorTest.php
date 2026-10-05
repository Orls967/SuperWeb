<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Manufacturing\Application\Services\ProductionService;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\MaterialBalance;
use Modules\Manufacturing\Domain\Models\MaterialIssue;
use Modules\Manufacturing\Domain\Models\MaterialIssueLot;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Domain\Models\ProductionOrder;
use Modules\Manufacturing\Domain\Models\WorkCenter;
use Tests\TestCase;

/**
 * Regresi Fase 37: state machine order, issue FIFO/FEFO + alokasi lot,
 * backflush, FG receipt guard, downtime, WIP, scrap→NCR, subkontrak, invarian.
 */
class ShopFloorTest extends TestCase
{
    use RefreshDatabase;

    private ProductionService $production;

    private ManufacturingService $mfg;

    private User $admin;

    private Material $fg;

    private Material $raw;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->production = app(ProductionService::class);
        $this->mfg = app(ManufacturingService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();

        $this->fg = $this->mfg->createMaterial(['code' => 'FG-SF', 'name' => 'Barang jadi shop floor', 'kind' => 'finished', 'base_uom' => 'pcs']);
        $this->raw = $this->mfg->createMaterial(['code' => 'RM-SF', 'name' => 'Bahan shop floor', 'kind' => 'raw', 'base_uom' => 'kg']);

        $this->mfg->createBom([
            'output_material_id' => $this->fg->id,
            'name' => 'BOM shop floor',
            'effective_from' => now()->toDateString(),
            'lines' => [['input_material_id' => $this->raw->id, 'qty' => 2, 'uom' => 'kg']],
        ], $this->admin);
    }

    private function makeOrder(float $qty = 10, string $kind = 'standard'): ProductionOrder
    {
        return $this->production->createProductionOrder([
            'material_id' => $this->fg->id,
            'qty' => $qty,
            'kind' => $kind,
            'due_date' => now()->addDays(5)->toDateString(),
        ], $this->admin);
    }

    /** @return array{a: MaterialLot, b: MaterialLot} */
    private function seedLots(): array
    {
        $a = MaterialLot::create([
            'material_id' => $this->raw->id, 'lot_number' => 'LOT-A',
            'qty' => 5, 'produced_at' => '2026-01-01', 'expiry_date' => '2026-12-31',
            'source_type' => 'manual', 'status' => 'active',
        ]);
        $b = MaterialLot::create([
            'material_id' => $this->raw->id, 'lot_number' => 'LOT-B',
            'qty' => 10, 'produced_at' => '2026-06-01', 'expiry_date' => '2026-07-31',
            'source_type' => 'manual', 'status' => 'active',
        ]);
        MaterialBalance::create(['material_id' => $this->raw->id, 'qty_on_hand' => 15, 'qty_reserved' => 0]);

        return ['a' => $a, 'b' => $b];
    }

    // ── 37.1 State machine order ─────────────────────────────────────────

    public function test_order_lifecycle_and_invalid_transition(): void
    {
        $order = $this->makeOrder();

        $this->assertSame('planned', $order->status);
        $this->assertStringStartsWith('MPO/', $order->number);

        $this->production->transition($order, 'released', $this->admin);
        $this->assertSame('released', $order->fresh()->status);

        // Transisi lompat: released → completed tidak sah.
        try {
            $this->production->transition($order->fresh(), 'completed', $this->admin);
            $this->fail('Transisi lompat harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak sah', $e->getMessage());
        }

        // Replay idempoten.
        $again = $this->production->transition($order->fresh(), 'released', $this->admin);
        $this->assertSame('released', $again->status);
    }

    // ── 37.2 Issue FIFO/FEFO + alokasi lot ───────────────────────────────

    public function test_fifo_issue_consumes_oldest_lot_first_and_allocates(): void
    {
        $lots = $this->seedLots();
        $order = $this->makeOrder();
        $this->production->transition($order, 'released', $this->admin);

        $issues = $this->production->issueMaterials($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 7, 'method' => 'fifo'],
        ], $this->admin);

        $issue = $issues[0];
        $this->assertNull($issue->alert);
        $this->assertEqualsWithDelta(7.0, (float) $issue->qty, 0.0001);

        // FIFO: lot A (lebih tua) habis duluan, sisanya dari lot B.
        $allocations = MaterialIssueLot::where('material_issue_id', $issue->id)->get()->keyBy('lot_id');
        $this->assertEqualsWithDelta(5.0, (float) $allocations[$lots['a']->id]->qty, 0.0001);
        $this->assertEqualsWithDelta(2.0, (float) $allocations[$lots['b']->id]->qty, 0.0001);

        $this->assertSame('consumed', $lots['a']->fresh()->status);
        $this->assertEqualsWithDelta(8.0, (float) $lots['b']->fresh()->qty, 0.0001);
        $this->assertEqualsWithDelta(8.0, (float) MaterialBalance::where('material_id', $this->raw->id)->firstOrFail()->qty_on_hand, 0.0001);
    }

    public function test_fefo_issue_consumes_nearest_expiry_first(): void
    {
        $lots = $this->seedLots();
        $order = $this->makeOrder();
        $this->production->transition($order, 'released', $this->admin);

        $issues = $this->production->issueMaterials($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 4, 'method' => 'fefo'],
        ], $this->admin);

        $allocations = MaterialIssueLot::where('material_issue_id', $issues[0]->id)->get()->keyBy('lot_id');
        $this->assertEqualsWithDelta(4.0, (float) $allocations[$lots['b']->id]->qty, 0.0001, 'Lot B kedaluwarsa lebih dulu (Juli vs Desember).');
        $this->assertArrayNotHasKey($lots['a']->id, $allocations->all());
    }

    public function test_shortage_issues_partial_with_alert_and_never_negative(): void
    {
        $this->seedLots();
        $order = $this->makeOrder();
        $this->production->transition($order, 'released', $this->admin);

        $issues = $this->production->issueMaterials($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 100, 'method' => 'fifo'],
        ], $this->admin);

        $this->assertSame('shortage', $issues[0]->alert);
        $this->assertEqualsWithDelta(15.0, (float) $issues[0]->qty, 0.0001, 'Semua stok terpakai, sisanya alert.');
        $this->assertEqualsWithDelta(0.0, (float) MaterialBalance::where('material_id', $this->raw->id)->firstOrFail()->qty_on_hand, 0.0001);
    }

    // ── 37.3 Operasi + backflush saat completed ──────────────────────────

    public function test_operation_reporting_accumulates_completed_qty(): void
    {
        $order = $this->makeOrder();
        $this->production->transition($order, 'released', $this->admin);

        $report = $this->production->startOperation($order->fresh(), 1, null, null);
        $this->assertSame('in_progress', $order->fresh()->status);

        $result = $this->production->finishOperation($report, 10, 0, 0);
        $this->assertEqualsWithDelta(10.0, (float) $result['order']->qty_completed, 0.0001);
        $this->assertGreaterThanOrEqual(1, $result['report']->duration_minutes);

        // Finish dua kali → idempoten.
        $again = $this->production->finishOperation($result['report'], 10, 0, 0);
        $this->assertEqualsWithDelta(10.0, (float) $again['order']->qty_completed, 0.0001);
    }

    public function test_backflush_issues_remaining_bom_needs(): void
    {
        $this->seedLots();
        $order = $this->makeOrder(); // 10 FG × 2 kg = 20 kg
        $this->production->transition($order, 'released', $this->admin);
        $this->production->issueMaterials($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 7, 'method' => 'fifo'],
        ], $this->admin);

        $report = $this->production->startOperation($order->fresh(), 1);
        $this->production->finishOperation($report, 10);

        $completed = $this->production->transition($order->fresh(), 'completed', $this->admin);

        // Stok hanya 15 kg: 7 manual + 8 backflush, sisanya alert shortage.
        $totalIssued = (float) MaterialIssue::where('production_order_id', $completed->id)->sum('qty');
        $this->assertEqualsWithDelta(15.0, $totalIssued, 0.0001, 'Backflush melengkapi sampai batas stok (15 kg).');
        $this->assertEqualsWithDelta(0.0, (float) MaterialBalance::where('material_id', $this->raw->id)->firstOrFail()->qty_on_hand, 0.0001);

        $backflush = MaterialIssue::where('production_order_id', $completed->id)
            ->orderByDesc('created_at')->firstOrFail();
        $this->assertSame('shortage', $backflush->alert, 'Sisa kebutuhan yang tidak terpenuhi ditandai shortage.');
        $this->assertSame('backflush', $backflush->kind);
    }

    // ── 37.5 FG receipt guard ────────────────────────────────────────────

    public function test_fg_receipt_cannot_exceed_field_output(): void
    {
        $order = $this->makeOrder();
        $this->production->transition($order, 'released', $this->admin);
        $report = $this->production->startOperation($order->fresh(), 1);
        $this->production->finishOperation($report, 6);

        $receipt = $this->production->receiveFg($order->fresh(), 6, $this->admin, 'FG-LOT-1');
        $this->assertEqualsWithDelta(6.0, (float) $receipt->qty, 0.0001);

        // Lot & saldo FG terbentuk.
        $lot = MaterialLot::where('lot_number', 'FG-LOT-1')->firstOrFail();
        $this->assertEqualsWithDelta(6.0, (float) $lot->qty, 0.0001);
        $this->assertSame('production', $lot->source_type);

        try {
            $this->production->receiveFg($order->fresh(), 5, $this->admin);
            $this->fail('FG receipt melebihi qty_completed harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('melebihi hasil lapangan', $e->getMessage());
        }
    }

    // ── 37.4 Downtime ────────────────────────────────────────────────────

    public function test_downtime_logs_start_and_end_idempotently(): void
    {
        $plant = $this->mfg->createPlant(['code' => 'PLT-SF', 'name' => 'Pabrik SF', 'type' => 'factory']);
        $wc = WorkCenter::create([
            'plant_id' => $plant->id, 'code' => 'WC-SF', 'name' => 'Mesin uji', 'kind' => 'machine',
            'capacity_per_hour' => 10, 'efficiency_percent' => 100,
            'machine_cost_per_hour_idr' => 0, 'labor_cost_per_hour_idr' => 0, 'overhead_per_hour_idr' => 0,
        ]);

        $log = $this->production->startDowntime($wc->id, 'machine_down', $this->admin, null, 'Poros aus');
        $this->assertNull($log->ended_at);

        $ended = $this->production->endDowntime($log);
        $this->assertNotNull($ended->ended_at);
        $this->assertGreaterThanOrEqual(1, $ended->minutes);

        // Idempoten.
        $again = $this->production->endDowntime($ended);
        $this->assertTrue($again->ended_at->equalTo($ended->ended_at));

        try {
            $this->production->startDowntime($wc->id, 'not_a_reason', $this->admin);
            $this->fail('Kode alasan tidak sah harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Kode downtime', $e->getMessage());
        }
    }

    // ── 37.6 WIP ─────────────────────────────────────────────────────────

    public function test_wip_transfer_lifecycle_and_report(): void
    {
        $order = $this->makeOrder();
        $this->production->transition($order, 'released', $this->admin);

        $transfer = $this->production->transferWip($order->fresh(), null, null, 4, $this->admin);
        $this->assertSame('in_transit', $transfer->status);

        $received = $this->production->receiveWip($transfer);
        $this->assertSame('received', $received->status);

        // Idempoten.
        $again = $this->production->receiveWip($received);
        $this->assertSame('received', $again->status);

        // Laporan hanya menghitung yang in_transit.
        $report = $this->production->wipReport();
        $this->assertArrayNotHasKey($order->id, $report);

        $pending = $this->production->transferWip($order->fresh(), null, null, 3, $this->admin);
        $report = $this->production->wipReport($order->id);
        $this->assertEqualsWithDelta(3.0, $report[$order->id]['qty'], 0.0001);
        $this->assertSame(1, $report[$order->id]['count']);
        unset($pending);
    }

    // ── 37.7 Scrap → NCR ─────────────────────────────────────────────────

    public function test_scrap_beyond_tolerance_flags_ncr(): void
    {
        $order = $this->makeOrder(10); // toleransi default 2%
        $this->production->transition($order, 'released', $this->admin);
        $report = $this->production->startOperation($order->fresh(), 1);
        $this->production->finishOperation($report, 10);

        $small = $this->production->recordScrapRework($order->fresh(), 'scrap', 0.1, 'Cacat kecil', $this->admin);
        $this->assertFalse($small['ncrRequired'], 'Scrap 1% ≤ toleransi 2%.');

        $big = $this->production->recordScrapRework($order->fresh()->fresh(), 'scrap', 3, 'Cacat berat', $this->admin, 50_000);
        $this->assertTrue($big['ncrRequired'], 'Scrap kumulatif melampaui 2%.');
        $this->assertTrue($big['record']->ncr_required);

        // Scrap mengurangi hasil lapangan.
        $this->assertEqualsWithDelta(6.9, (float) $order->fresh()->qty_completed, 0.0001);

        try {
            $this->production->recordScrapRework($order->fresh(), 'invalid', 1, 'x', $this->admin);
            $this->fail('Jenis tidak sah harus ditolak.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    // ── 37.8 Subkontrak ──────────────────────────────────────────────────

    public function test_subcontract_ship_receive_and_service_pr(): void
    {
        $this->seedLots();
        $order = $this->makeOrder(10, 'subcontract');
        $this->production->transition($order, 'released', $this->admin);

        $shipped = $this->production->shipToSubcontract($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 5, 'method' => 'fifo'],
        ], 10, $this->admin, null, null, [
            'origin_code' => 'MFG', 'street' => 'Jl. Pabrik', 'city' => 'Banjarmasin',
            'consignee_name' => 'Subkon Maklon', 'declared_value_idr' => 100_000,
        ]);

        $this->assertSame('shipping', $shipped['receipt']->status);
        $this->assertEqualsWithDelta(10.0, (float) $shipped['receipt']->qty_in, 0.0001);
        $this->assertNotNull($shipped['receipt']->shipment_ref, 'Booking Logistics mengisi shipment_ref (37.8).');

        // Idempoten: booking ulang order sama tidak membuat shipment kedua.
        $again = $this->production->shipToSubcontract($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 1, 'method' => 'fifo'],
        ], 1, $this->admin, null, null, ['origin_code' => 'MFG', 'street' => 'Jl. Pabrik', 'city' => 'Banjarmasin']);
        $this->assertSame($shipped['receipt']->shipment_ref, $again['receipt']->shipment_ref);
        $this->assertSame(1, Shipment::where('source_type', 'manufacturing_subcontract')
            ->where('source_id', $order->id)->count());

        $this->production->transition($order->fresh(), 'in_progress', $this->admin);

        $result = $this->production->receiveFromSubcontract($shipped['receipt'], 8, 1_200_000, $this->admin);

        $this->assertSame('received', $result['receipt']->status);
        $this->assertNotNull($result['prRef'], 'Biaya jasa menghasilkan PR (36.6 contract).');
        $this->assertStringStartsWith('PR/', $result['prRef']);

        // Hasil olahan masuk stok FG.
        $this->assertEqualsWithDelta(8.0, (float) MaterialBalance::firstWhere('material_id', $this->fg->id)->qty_on_hand, 0.0001);

        // Replay aman.
        $replay = $this->production->receiveFromSubcontract($result['receipt'], 8, 1_200_000, $this->admin);
        $this->assertSame($result['prRef'], $replay['prRef']);
        $this->assertEqualsWithDelta(8.0, (float) MaterialBalance::firstWhere('material_id', $this->fg->id)->qty_on_hand, 0.0001);
    }

    // ── 37.9 Invarian ────────────────────────────────────────────────────

    public function test_invariants_pass_for_consistent_order(): void
    {
        $this->seedLots();
        $order = $this->makeOrder(10);
        $this->production->transition($order, 'released', $this->admin);
        $this->production->issueMaterials($order->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 20, 'method' => 'fifo'],
        ], $this->admin);
        $report = $this->production->startOperation($order->fresh(), 1);
        $this->production->finishOperation($report, 10);

        $result = $this->production->checkInvariants($order->fresh());
        $this->assertTrue($result['ok'], implode('; ', $result['issues']));
    }

    public function test_invariants_detect_issued_excess(): void
    {
        $this->seedLots();
        $order = $this->makeOrder(10);
        $this->production->transition($order, 'released', $this->admin);

        // Issue 14 kg padahal kebutuhan 20 kg aman — buat kelebihan: pakai order kecil.
        $small = $this->makeOrder(1);
        $this->production->transition($small, 'released', $this->admin);
        $this->production->issueMaterials($small->fresh(), [
            ['material_id' => $this->raw->id, 'qty' => 3, 'method' => 'fifo'],
        ], $this->admin);

        $result = $this->production->checkInvariants($small->fresh());
        $this->assertFalse($result['ok'], 'Issue 3 kg > kebutuhan 1 × 2 kg + 5% harus melanggar.');
        $this->assertStringContainsString('terlalu banyak', $result['issues'][0]);
    }

    // ── Route render ─────────────────────────────────────────────────────

    public function test_production_page_renders(): void
    {
        $this->makeOrder();

        $this->actingAs($this->admin)
            ->get(route('manufacturing.production.index'))
            ->assertOk()
            ->assertSee('Shop Floor');
    }
}
