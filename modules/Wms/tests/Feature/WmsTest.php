<?php

declare(strict_types=1);

namespace Tests\Feature\Wms;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Store\Domain\Models\Category;
use Modules\Store\Domain\Models\Product;
use Modules\Wms\Application\Services\WmsService;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\Replenishment;
use Modules\Wms\Domain\Models\Task;
use Tests\TestCase;

/**
 * Regresi Fase 41: hirarki gudang, putaway/pick + kapasitas, wave guard,
 * transfer ship/receive idempoten, cycle count four-eyes + stok tidak
 * negatif, replenishment/slotting, dock overlap, packing list, wms:audit.
 */
class WmsTest extends TestCase
{
    use RefreshDatabase;

    private WmsService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->service = app(WmsService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    private function makeBin(int $capacity = 0, bool $pickFace = false): array
    {
        $warehouse = $this->service->createWarehouse(['code' => 'W-'.uniqid(), 'name' => 'Gudang uji', 'kind' => 'dc', 'city' => 'Banjarmasin']);
        $zone = $this->service->createZone($warehouse, 'S1', 'Storage');
        $rack = $this->service->createRack($zone, 'R1');
        $bin = $this->service->createBin($rack, 'B-01', $capacity, $pickFace);

        return [$warehouse, $bin];
    }

    private function makeProductId(): int
    {
        $category = Category::create(['name' => 'WMS', 'slug' => 'wms-'.uniqid()]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Produk WMS', 'slug' => 'produk-wms-'.uniqid(),
            'sku' => 'WMS-'.uniqid(), 'price' => 10_000, 'cached_stock' => 100, 'is_car' => false,
        ]);

        return (int) $product->id;
    }

    // ── 41.1 Hirarki ─────────────────────────────────────────────────────

    public function test_warehouse_hierarchy_is_created_and_codes_unique(): void
    {
        [$warehouse] = $this->makeBin();

        $zone = $this->service->createZone($warehouse, 'S1', 'Storage');
        $this->assertSame('S1', $zone->code);

        try {
            $this->service->createWarehouse(['code' => $warehouse->code, 'name' => 'Duplikat']);
            $this->fail('Kode gudang ganda harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sudah dipakai', $e->getMessage());
        }
    }

    // ── 41.2 Putaway / pick ──────────────────────────────────────────────

    public function test_putaway_and_pick_obey_capacity_and_never_go_negative(): void
    {
        [$warehouse, $bin] = $this->makeBin(capacity: 10);
        $productId = $this->makeProductId();

        $result = $this->service->putaway($bin, $productId, 6, $this->admin, 'LOT-A');
        $this->assertSame('done', $result['task']->status);
        $this->assertEqualsWithDelta(6.0, (float) $result['stock']->qty, 0.0001);

        // Kapasitas 10, isi 6 → +5 melewati → ditolak.
        try {
            $this->service->putaway($bin, $productId, 5, $this->admin, 'LOT-A');
            $this->fail('Kapasitas bin harus menolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Kapasitas', $e->getMessage());
        }

        $this->service->pick($bin, $productId, 4, $this->admin, 'LOT-A', 'fifo');
        $stock = BinStock::where('bin_id', $bin->id)->firstOrFail();
        $this->assertEqualsWithDelta(2.0, (float) $stock->qty, 0.0001);

        // Lebih banyak dari stok → ditolak (tidak negatif).
        try {
            $this->service->pick($bin, $productId, 5, $this->admin, 'LOT-A', 'fifo');
            $this->fail('Pick melebihi stok harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak mencukupi', $e->getMessage());
        }
        $this->assertEqualsWithDelta(2.0, (float) $stock->fresh()->qty, 0.0001);
        unset($warehouse);
    }

    // ── 41.3 Wave ────────────────────────────────────────────────────────

    public function test_wave_release_guards_and_idempotent_task_close(): void
    {
        [$warehouse, $bin] = $this->makeBin();
        $productId = $this->makeProductId();

        $wave = $this->service->createWave('fefo', 'Uji wave');

        // Wave kosong tidak bisa dirilis.
        try {
            $this->service->releaseWave($wave);
            $this->fail('Wave kosong harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('kosong', $e->getMessage());
        }

        $task = $this->service->addTaskToWave($wave, $productId, 3, $this->admin, $bin, 'LOT-W');
        $released = $this->service->releaseWave($wave);
        $this->assertSame('released', $released->status);
        $this->assertNotNull($released->released_at);

        // Tidak bisa menambah setelah rilis.
        try {
            $this->service->addTaskToWave($released, $productId, 1, $this->admin, $bin);
            $this->fail('Tambahan setelah rilis harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('open', $e->getMessage());
        }

        $done = $this->service->closeTask($task);
        $this->assertSame('done', $done->status);
        $again = $this->service->closeTask($done);
        $this->assertSame($done->id, $again->id);
        unset($warehouse);
    }

    // ── 41.4 Transfer ────────────────────────────────────────────────────

    public function test_transfer_ship_and_receive_move_stock_idempotently(): void
    {
        $from = $this->service->createWarehouse(['code' => 'T-FROM', 'name' => 'Asal', 'kind' => 'dc']);
        $to = $this->service->createWarehouse(['code' => 'T-TO', 'name' => 'Tujuan', 'kind' => 'fg']);
        $zoneF = $this->service->createZone($from, 'S1', 'Storage');
        $rackF = $this->service->createRack($zoneF, 'R1');
        $binF = $this->service->createBin($rackF, 'B1');
        $zoneT = $this->service->createZone($to, 'S1', 'Storage');
        $rackT = $this->service->createRack($zoneT, 'R1');
        $binT = $this->service->createBin($rackT, 'B1');

        $productId = $this->makeProductId();
        $this->service->putaway($binF, $productId, 8, $this->admin, 'TR-LOT');

        $transfer = $this->service->createTransfer($from, $to, [
            ['product_id' => $productId, 'qty' => 5, 'lot_number' => 'TR-LOT'],
        ], $this->admin);

        $shipped = $this->service->shipTransfer($transfer, $this->admin);
        $this->assertSame('in_transit', $shipped->status);
        $this->assertSame('picked', $shipped->lines->first()->status);
        // 41.7: outbound DC → shipment otomatis; resi tersimpan di transfer.
        $this->assertNotNull($shipped->tracking_number, 'Booking Logistics mengisi tracking_number.');
        $this->assertEqualsWithDelta(3.0, (float) BinStock::where('bin_id', $binF->id)->sum('qty'), 0.0001);

        // Replay ship idempoten.
        $again = $this->service->shipTransfer($shipped, $this->admin);
        $this->assertSame('in_transit', $again->status);
        $this->assertEqualsWithDelta(3.0, (float) BinStock::where('bin_id', $binF->id)->sum('qty'), 0.0001);

        $received = $this->service->receiveTransfer($shipped, $binT, $this->admin);
        $this->assertSame('received', $received->status);
        $this->assertEqualsWithDelta(5.0, (float) BinStock::where('bin_id', $binT->id)->sum('qty'), 0.0001);

        // Replay receive idempoten (tidak menambah dua kali).
        $replay = $this->service->receiveTransfer($received, $binT, $this->admin);
        $this->assertSame('received', $replay->status);
        $this->assertEqualsWithDelta(5.0, (float) BinStock::where('bin_id', $binT->id)->sum('qty'), 0.0001);
    }

    // ── 41.5 Cycle count four-eyes ───────────────────────────────────────

    public function test_cycle_count_adjustment_requires_four_eyes(): void
    {
        [$warehouse, $bin] = $this->makeBin();
        $productId = $this->makeProductId();
        $this->service->putaway($bin, $productId, 10, $this->admin, 'CC-LOT');

        $count = $this->service->startCycleCount($warehouse, [
            ['bin_id' => $bin->id, 'product_id' => $productId, 'counted_qty' => 8, 'lot_number' => 'CC-LOT'],
        ], $this->admin);

        $this->assertSame('draft', $count->status);
        $this->assertEqualsWithDelta(-2.0, (float) $count->variance_qty, 0.0001);
        $this->assertLessThan(100.0, (float) $count->accuracy_percent);

        // Variance ada → ajukan approval.
        $submitted = $this->service->submitAdjustment($count, $this->admin);
        $this->assertSame('pending_approval', $submitted->status);
        $this->assertNotNull($submitted->approval_id);

        // Four-eyes: pengaju tidak boleh menyetujui.
        try {
            $this->service->approveAndApply($submitted, $this->admin);
            $this->fail('Four-eyes dilanggar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Four-Eyes', $e->getMessage());
        }

        $reviewer = User::factory()->create(['role' => 'admin']);
        $applied = $this->service->approveAndApply($submitted, $reviewer);
        $this->assertSame('applied', $applied->status);
        $this->assertEqualsWithDelta(8.0, (float) BinStock::where('bin_id', $bin->id)->sum('qty'), 0.0001);

        // Replay idempoten.
        $again = $this->service->approveAndApply($applied, $reviewer);
        $this->assertSame('applied', $again->status);
        $this->assertEqualsWithDelta(8.0, (float) BinStock::where('bin_id', $bin->id)->sum('qty'), 0.0001);
    }

    public function test_zero_variance_count_is_rejected(): void
    {
        [$warehouse, $bin] = $this->makeBin();
        $productId = $this->makeProductId();
        $this->service->putaway($bin, $productId, 5, $this->admin);

        $count = $this->service->startCycleCount($warehouse, [
            ['bin_id' => $bin->id, 'product_id' => $productId, 'counted_qty' => 5],
        ], $this->admin);

        try {
            $this->service->submitAdjustment($count, $this->admin);
            $this->fail('Tanpa selisih tidak perlu adjustment.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Tidak ada selisih', $e->getMessage());
        }
    }

    // ── 41.6 Replenishment ──────────────────────────────────────────────

    public function test_replenishment_creates_task_when_below_minimum(): void
    {
        [$warehouse, $bin] = $this->makeBin(pickFace: true);
        $productId = $this->makeProductId();

        $this->service->setPickFace($bin, $productId, 4, 12);
        $results = $this->service->computeReplenishment();

        $first = $results[0];
        $this->assertTrue($first->isBelowMin());
        $this->assertEqualsWithDelta(12.0, (float) $first->suggested_qty, 0.0001);
        $this->assertSame(1, Task::where('kind', 'replenish')->where('status', 'open')->count());

        // Isi penuh → tidak ada saran baru.
        $this->service->putaway($bin, $productId, 12, $this->admin);
        $results = $this->service->computeReplenishment();
        $this->assertEqualsWithDelta(0.0, (float) $results[0]->suggested_qty, 0.0001);
        unset($warehouse);
    }

    // ── 41.7 Dock overlap + packing list ────────────────────────────────

    public function test_dock_overlap_rejected_and_packing_list_print(): void
    {
        $warehouse = $this->service->createWarehouse(['code' => 'D-01', 'name' => 'Dok Uji', 'kind' => 'dc']);

        $this->service->scheduleDock($warehouse, 'in', 'PO-1', now()->addHour()->toDateTimeString(), now()->addHours(2)->toDateTimeString());

        try {
            $this->service->scheduleDock($warehouse, 'in', 'PO-2', now()->addMinutes(90)->toDateTimeString(), now()->addHours(3)->toDateTimeString());
            $this->fail('Dock overlap harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('bertabrakan', $e->getMessage());
        }

        // Packing list.
        $to = $this->service->createWarehouse(['code' => 'D-02', 'name' => 'Tujuan', 'kind' => 'fg']);
        $productId = $this->makeProductId();
        $transfer = $this->service->createTransfer($warehouse, $to, [['product_id' => $productId, 'qty' => 2]], $this->admin);

        $list = $this->service->createPackingList($transfer, $this->admin, 'RESI-001');
        $this->assertStringStartsWith('PL/', $list->number);
        $this->assertSame('RESI-001', $list->tracking_number);
        $this->assertCount(1, $list->items);

        $printed = $this->service->markPackingListPrinted($list);
        $this->assertSame('printed', $printed->status);
        // Replay dicetak → idempoten.
        $again = $this->service->markPackingListPrinted($printed);
        $this->assertSame('printed', $again->status);
        unset($transfer);
    }

    // ── 41.8 Audit ───────────────────────────────────────────────────────

    public function test_wms_audit_passes_after_putaway(): void
    {
        [$warehouse, $bin] = $this->makeBin();
        $productId = $this->makeProductId();
        $this->service->putaway($bin, $productId, 7, $this->admin, 'AUD-LOT');

        $this->artisan('wms:audit')->assertSuccessful();
        unset($warehouse);
    }

    // ── Route render ─────────────────────────────────────────────────────

    public function test_wms_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('wms.index'))
            ->assertOk()
            ->assertSee('Gudang');
    }
}
