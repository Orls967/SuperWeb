<?php

declare(strict_types=1);

namespace Modules\ControlTower\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\ControlTower\Contracts\ItemAvailabilityReader;
use Modules\ControlTower\Contracts\PromiseQuoteInterface;
use Modules\ControlTower\Domain\Algorithms\AtpCtpNetter;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\Core\Domain\Exceptions\ApiIdempotencyConflictException;
use Modules\Distribution\Contracts\OpenDemandProvider;
use Modules\Manufacturing\Contracts\CapacityAndMaterialProvider;
use Modules\Store\Contracts\Product;
use Tests\TestCase;

final class Slice1AtpCtpPromiseTest extends TestCase
{
    use RefreshDatabase;

    public function test_i1_netter_atp_sufficient_on_time_with_quarantine_hold_and_lookahead_protection(): void
    {
        $netter = new AtpCtpNetter;

        // onHand=150, quarantine=20, hold=30 => initial available = 100
        // mps=[20, 50, 0, 0], commitments=[40, 10, 0, 0]
        // running: P1=100+20-40=80, P2=80+50-10=120, P3=120, P4=120
        $res = $netter->net(
            itemCode: 'SKU-I1-01',
            onHand: 150,
            quarantine: 20,
            hold: 30,
            mpsReceipts: [20, 50, 0, 0],
            commitments: [40, 10, 0, 0],
            capacityCumulative: [10, 20, 30, 40],
            materialCumulative: [10, 20, 30, 40],
            requestedQty: 75,
            requestedPeriod: 1,
        );

        $this->assertSame('on_time', $res['status']);
        $this->assertSame(1, $res['promised_period']);
        $this->assertSame(0, $res['late_periods']);
        $this->assertSame(80, $res['atp_cumulative']);
        $this->assertSame(90, $res['ctp_cumulative']);
        $this->assertSame(75, $res['atp_confirmed_qty']);
        $this->assertSame(0, $res['ctp_manufacturing_qty']);
        $this->assertNull($res['reason_code']);
        $this->assertSame(64, strlen($res['certificate_hash']));

        // Deterministik SHA-256
        $repeat = $netter->net(
            itemCode: 'SKU-I1-01',
            onHand: 150,
            quarantine: 20,
            hold: 30,
            mpsReceipts: [20, 50, 0, 0],
            commitments: [40, 10, 0, 0],
            capacityCumulative: [10, 20, 30, 40],
            materialCumulative: [10, 20, 30, 40],
            requestedQty: 75,
            requestedPeriod: 1,
        );
        $this->assertSame($res['certificate_hash'], $repeat['certificate_hash']);
    }

    public function test_i1_netter_ctp_on_time_and_ctp_late_vectors(): void
    {
        $netter = new AtpCtpNetter;

        // ATP di P1 = 30 (< 70), tapi CTP di P1 = 30 + min(50, 60) = 80 (>= 70) -> on_time via CTP
        $onTimeCtp = $netter->net(
            itemCode: 'SKU-I1-02',
            onHand: 50,
            quarantine: 0,
            hold: 20,
            mpsReceipts: [0, 0, 0],
            commitments: [0, 0, 0],
            capacityCumulative: [50, 100, 150],
            materialCumulative: [60, 120, 180],
            requestedQty: 70,
            requestedPeriod: 1,
        );

        $this->assertSame('on_time', $onTimeCtp['status']);
        $this->assertSame(1, $onTimeCtp['promised_period']);
        $this->assertSame(0, $onTimeCtp['late_periods']);
        $this->assertSame(30, $onTimeCtp['atp_cumulative']);
        $this->assertSame(80, $onTimeCtp['ctp_cumulative']);
        $this->assertSame(30, $onTimeCtp['atp_confirmed_qty']);
        $this->assertSame(40, $onTimeCtp['ctp_manufacturing_qty']);
        $this->assertNull($onTimeCtp['reason_code']);

        // Diminta di P1 qty 120: P1 CTP=80 (<120), P2 CTP=30+min(100,120)=130 (>=120) -> late 1 periode
        $lateCtp = $netter->net(
            itemCode: 'SKU-I1-02',
            onHand: 50,
            quarantine: 0,
            hold: 20,
            mpsReceipts: [0, 0, 0],
            commitments: [0, 0, 0],
            capacityCumulative: [50, 100, 150],
            materialCumulative: [60, 120, 180],
            requestedQty: 120,
            requestedPeriod: 1,
        );

        $this->assertSame('late', $lateCtp['status']);
        $this->assertSame(2, $lateCtp['promised_period']);
        $this->assertSame(1, $lateCtp['late_periods']);
        $this->assertSame(30, $lateCtp['atp_cumulative']);
        $this->assertSame(130, $lateCtp['ctp_cumulative']);
        $this->assertNull($lateCtp['reason_code']);
    }

    public function test_i1_netter_bottleneck_and_boundary_reason_codes(): void
    {
        $netter = new AtpCtpNetter;

        // Capacity bottleneck (cap=40 < mat=200)
        $capBottleneck = $netter->net(
            itemCode: 'SKU-CAP',
            onHand: 10,
            quarantine: 0,
            hold: 0,
            mpsReceipts: [0, 0, 0],
            commitments: [0, 0, 0],
            capacityCumulative: [15, 30, 40],
            materialCumulative: [100, 150, 200],
            requestedQty: 80,
            requestedPeriod: 1,
        );
        $this->assertSame('cannot_promise', $capBottleneck['status']);
        $this->assertNull($capBottleneck['promised_period']);
        $this->assertSame('INSUFFICIENT_CAPACITY', $capBottleneck['reason_code']);

        // Material bottleneck (mat=25 < cap=200)
        $matBottleneck = $netter->net(
            itemCode: 'SKU-MAT',
            onHand: 10,
            quarantine: 0,
            hold: 0,
            mpsReceipts: [0, 0, 0],
            commitments: [0, 0, 0],
            capacityCumulative: [80, 140, 200],
            materialCumulative: [10, 20, 25],
            requestedQty: 80,
            requestedPeriod: 2,
        );
        $this->assertSame('cannot_promise', $matBottleneck['status']);
        $this->assertNull($matBottleneck['promised_period']);
        $this->assertSame('INSUFFICIENT_MATERIAL', $matBottleneck['reason_code']);

        // Invalid quantity
        $invalidQty = $netter->net('SKU-INV', 10, 0, 0, [0], [0], [10], [10], 0, 1);
        $this->assertSame('cannot_promise', $invalidQty['status']);
        $this->assertSame('INVALID_QUANTITY', $invalidQty['reason_code']);

        // Horizon exceeded
        $horizonExceeded = $netter->net('SKU-HOR', 10, 0, 0, [0, 0], [0, 0], [10, 20], [10, 20], 5, 5);
        $this->assertSame('cannot_promise', $horizonExceeded['status']);
        $this->assertSame('HORIZON_EXCEEDED', $horizonExceeded['reason_code']);
    }

    public function test_real_cross_module_adapters_and_promise_quote_idempotency(): void
    {
        $asOf = '2026-11-14';
        $sku = 'ITEM-REAL-01';

        // 1. Seed Store Product + WMS Warehouse + Bin Stock (available = 60, quarantine = 10)
        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'sku' => $sku,
            'name' => 'Bearing Presisi 01',
            'slug' => 'bearing-presisi-01',
            'price' => 100000,
        ]);

        $whId = (string) Str::uuid();
        DB::table('wms_warehouses')->insert([
            'id' => $whId,
            'code' => 'WH-MAIN',
            'name' => 'Gudang Utama',
            'kind' => 'dc',
            'is_active' => true,
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        $zoneId = DB::table('wms_zones')->insertGetId([
            'warehouse_id' => $whId,
            'code' => 'Z-A',
            'name' => 'Zone A',
            'kind' => 'storage',
            'is_active' => true,
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        $rackId = DB::table('wms_racks')->insertGetId([
            'zone_id' => $zoneId,
            'code' => 'R-1',
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        $binId = DB::table('wms_bins')->insertGetId([
            'rack_id' => $rackId,
            'code' => 'B-1',
            'is_active' => true,
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        DB::table('wms_bin_stocks')->insert([
            [
                'bin_id' => $binId,
                'product_id' => $product->id,
                'lot_number' => 'LOT-A',
                'status' => 'available',
                'stock_status' => 'available',
                'qty' => '60.000000',
                'qty_base' => 60,
                'created_at' => $asOf.' 00:00:00',
                'updated_at' => $asOf.' 00:00:00',
            ],
            [
                'bin_id' => $binId,
                'product_id' => $product->id,
                'lot_number' => 'LOT-Q',
                'status' => 'quarantine',
                'stock_status' => 'quarantine',
                'qty' => '10.000000',
                'qty_base' => 10,
                'created_at' => $asOf.' 00:00:00',
                'updated_at' => $asOf.' 00:00:00',
            ],
        ]);

        // 2. Seed Manufacturing tables (MPS, Material Balance, Scheduled Receipt, Work Center, Capacity Load)
        $matId = (string) Str::uuid();
        DB::table('mfg_materials')->insert([
            'id' => $matId,
            'code' => $sku,
            'name' => 'Bearing Presisi 01',
            'kind' => 'finished',
            'base_uom' => 'pcs',
            'is_active' => true,
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        $mpsHeaderId = (string) Str::uuid();
        DB::table('mfg_mps_headers')->insert([
            'id' => $mpsHeaderId,
            'name' => 'MPS-NOV',
            'version' => 1,
            'status' => 'active',
            'freeze_days' => 14,
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        DB::table('mfg_mps_lines')->insert([
            'header_id' => $mpsHeaderId,
            'material_id' => $matId,
            'period_start' => '2026-11-21', // Period 2 (+7 hari)
            'qty' => '40.000000',
            'frozen' => true,
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        DB::table('mfg_material_balances')->insert([
            'id' => (string) Str::uuid(),
            'material_id' => $matId,
            'qty_on_hand' => '30.000000',
            'qty_reserved' => '5.000000', // net starting material = 25
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        DB::table('mfg_scheduled_receipts')->insert([
            'id' => (string) Str::uuid(),
            'material_id' => $matId,
            'due_date' => '2026-11-21', // Period 2
            'qty' => '50.000000',
            'source_type' => 'po',
            'status' => 'open',
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        $plantId = (string) Str::uuid();
        DB::table('mfg_plants')->insert([
            'id' => $plantId,
            'code' => 'PLT-1',
            'name' => 'Plant 1',
            'is_active' => true,
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        $wcId = (string) Str::uuid();
        DB::table('mfg_work_centers')->insert([
            'id' => $wcId,
            'plant_id' => $plantId,
            'code' => 'WC-1',
            'name' => 'Assembly Line 1',
            'capacity_per_hour' => 10,
            'efficiency_percent' => 100,
            'is_active' => true,
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        $runId = (string) Str::uuid();
        DB::table('mfg_mrp_runs')->insert([
            'id' => $runId,
            'run_key' => hash('sha256', 'run-1'),
            'status' => 'completed',
            'horizon_days' => 84,
            'bucket_days' => 7,
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        // Period 1 free minutes = 180 -> 180*10/60 = 30 units; Period 2 free minutes = 240 -> 40 units (cum 70)
        DB::table('mfg_capacity_loads')->insert([
            [
                'run_id' => $runId,
                'work_center_id' => $wcId,
                'period_start' => '2026-11-14',
                'load_minutes' => 420,
                'capacity_minutes' => 600,
                'created_at' => $asOf.' 00:00:00',
                'updated_at' => $asOf.' 00:00:00',
            ],
            [
                'run_id' => $runId,
                'work_center_id' => $wcId,
                'period_start' => '2026-11-21',
                'load_minutes' => 360,
                'capacity_minutes' => 600,
                'created_at' => $asOf.' 00:00:00',
                'updated_at' => $asOf.' 00:00:00',
            ],
        ]);

        // 3. Seed Distribution tables (open commitment = 20 in Period 1, plus 1 delivered order for fulfillmentLines)
        $distId = (string) Str::uuid();
        DB::table('dist_distributors')->insert([
            'id' => $distId,
            'code' => 'DST-01',
            'name' => 'Distributor Nusantara',
            'status' => 'approved',
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        $openOrderId = (string) Str::uuid();
        DB::table('dist_orders')->insert([
            'id' => $openOrderId,
            'number' => 'DOR/DIST/2026-00001',
            'distributor_id' => $distId,
            'status' => 'allocated',
            'requested_date' => '2026-11-16', // Period 1
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);
        DB::table('dist_order_lines')->insert([
            'order_id' => $openOrderId,
            'product_id' => $product->id,
            'sku' => $sku,
            'name_snapshot' => 'Bearing Presisi 01',
            'qty' => '20.000000',
            'shipped_qty' => '0.000000',
            'line_status' => 'allocated',
            'created_at' => $asOf.' 00:00:00',
            'updated_at' => $asOf.' 00:00:00',
        ]);

        $deliveredOrderId = (string) Str::uuid();
        DB::table('dist_orders')->insert([
            'id' => $deliveredOrderId,
            'number' => 'DOR/DIST/2026-00000',
            'distributor_id' => $distId,
            'status' => 'delivered',
            'requested_date' => '2026-11-10',
            'delivered_at' => '2026-11-11 10:00:00',
            'created_at' => '2026-11-08 00:00:00',
            'updated_at' => '2026-11-11 10:00:00',
        ]);
        DB::table('dist_order_lines')->insert([
            'order_id' => $deliveredOrderId,
            'product_id' => $product->id,
            'sku' => $sku,
            'name_snapshot' => 'Bearing Presisi 01',
            'qty' => '15.000000',
            'shipped_qty' => '15.000000',
            'line_status' => 'delivered',
            'created_at' => '2026-11-08 00:00:00',
            'updated_at' => '2026-11-11 10:00:00',
        ]);
        DB::table('dist_shipments')->insert([
            'id' => (string) Str::uuid(),
            'number' => 'DSP/DIST/2026-00001',
            'order_id' => $deliveredOrderId,
            'status' => 'pod',
            'pod_at' => '2026-11-11 15:00:00',
            'created_at' => '2026-11-10 00:00:00',
            'updated_at' => '2026-11-11 15:00:00',
        ]);

        // Assert adapters directly
        $availReader = app(ItemAvailabilityReader::class);
        $pos = $availReader->positionFor($sku, $asOf);
        $this->assertSame(70, $pos['on_hand']);
        $this->assertSame(60, $pos['available']);
        $this->assertSame(10, $pos['quarantine']);
        $this->assertSame(0, $pos['hold']);

        $mfgProvider = app(CapacityAndMaterialProvider::class);
        $mps = $mfgProvider->mpsReceipts($sku, 4, $asOf);
        $this->assertSame([0, 40, 0, 0], $mps);
        $capMat = $mfgProvider->cumulativeByPeriod($sku, 4, $asOf);
        // P1 cap=30, P2 cap=70; P1 mat=25, P2 mat=75
        $this->assertSame(30, $capMat['capacity_cumulative'][0]);
        $this->assertSame(70, $capMat['capacity_cumulative'][1]);
        $this->assertSame(25, $capMat['material_cumulative'][0]);
        $this->assertSame(75, $capMat['material_cumulative'][1]);

        $distProvider = app(OpenDemandProvider::class);
        $this->assertSame([20, 0, 0, 0], $distProvider->commitmentsByPeriod($sku, 4, $asOf));
        $fulfillment = $distProvider->fulfillmentLines('2026-11-01', '2026-11-30');
        $this->assertCount(2, $fulfillment);
        $this->assertSame('DOR/DIST/2026-00000', $fulfillment[0]['order_ref']);
        $this->assertSame('2026-11-11', $fulfillment[0]['delivered_date']);

        // Execute PromiseQuoteInterface:
        // P1 ATP = 60 - 20 = 40; P1 producible = min(30, 25) = 25 -> P1 CTP = 65
        // P2 ATP = 40 + 40 = 80; P2 producible = min(70, 75) = 70 -> P2 CTP = 150
        // Quote 100 at period 1 -> cannot meet in P1 (65 < 100), fulfilled in P2 (150 >= 100) => late by 1 period!
        Event::fake(['tower.promise.made.v1']);
        $quoteService = app(PromiseQuoteInterface::class);
        $quote = $quoteService->quote(
            itemCode: $sku,
            quantity: 100,
            requestedPeriod: 1,
            idempotencyKey: 'IDEMP-REAL-001',
            orderReference: 'SO-REAL-001',
            asOfDate: $asOf,
        );

        $this->assertSame('late', $quote['status']);
        $this->assertSame(2, $quote['promised_period']);
        $this->assertSame(1, $quote['late_periods']);
        $this->assertSame(80, $quote['atp_cumulative']);
        $this->assertSame(150, $quote['ctp_cumulative']);
        $this->assertNull($quote['reason_code']);

        Event::assertDispatched('tower.promise.made.v1');
        $this->assertDatabaseHas('core_event_schemas', [
            'event_name' => 'tower.promise.made.v1',
            'version' => 1,
        ]);
        $this->assertDatabaseHas('core_outbox', [
            'event_type' => 'tower.promise.made.v1',
            'idempotency_key' => 'tower-promise-IDEMP-REAL-001',
        ]);

        // Idempotency hit: returns exact same result without duplicating row
        $hit = $quoteService->quote(
            itemCode: $sku,
            quantity: 100,
            requestedPeriod: 1,
            idempotencyKey: 'IDEMP-REAL-001',
            orderReference: 'SO-REAL-001',
            asOfDate: $asOf,
        );
        $this->assertSame($quote, $hit);
        $this->assertSame(1, OrderPromise::query()->where('idempotency_key', 'IDEMP-REAL-001')->count());

        // Idempotency conflict: different payload throws ApiIdempotencyConflictException
        $this->expectException(ApiIdempotencyConflictException::class);
        $quoteService->quote(
            itemCode: $sku,
            quantity: 105,
            requestedPeriod: 1,
            idempotencyKey: 'IDEMP-REAL-001',
            orderReference: 'SO-REAL-001',
            asOfDate: $asOf,
        );
    }

    public function test_echelon_fallback_and_atp_refresh_command_and_http_endpoints(): void
    {
        // Produk belum masuk store_products -> fallback ke EchelonStock
        EchelonStock::query()->create([
            'item_code' => 'ECH-ONLY-01',
            'item_name' => 'Echelon Fallback Item',
            'echelon_node' => 'DC',
            'on_hand_qty' => 200,
            'in_transit_qty' => 0,
            'reserved_qty' => 50,
            'safety_stock_qty' => 20,
            'abc_class' => 'A',
            'xyz_class' => 'X',
        ]);

        $pos = app(ItemAvailabilityReader::class)->positionFor('ECH-ONLY-01', '2026-11-14');
        $this->assertSame(200, $pos['on_hand']);
        $this->assertSame(50, $pos['hold']);
        $this->assertSame(150, $pos['available']);

        // Command tower:atp-refresh
        $this->artisan('tower:atp-refresh', ['--item' => 'ECH-ONLY-01', '--as-of' => '2026-11-14'])
            ->expectsOutputToContain('ATP/CTP horizon refreshed as of 2026-11-14 (1 item).')
            ->expectsOutputToContain('ECH-ONLY-01 | available=150')
            ->assertExitCode(0);

        // HTTP endpoints & role authorization
        $planner = User::factory()->create(['role' => 'planner']);
        $auditor = User::factory()->create(['role' => 'auditor']);

        // Auditor forbidden from POST quote
        $this->actingAs($auditor)
            ->postJson(route('control_tower.promises.quote'), [
                'item_code' => 'ECH-ONLY-01',
                'quantity' => 50,
                'requested_period' => 1,
                'idempotency_key' => 'HTTP-AUD-1',
            ])
            ->assertForbidden();

        // Planner creates quote via HTTP
        $response = $this->actingAs($planner)
            ->postJson(route('control_tower.promises.quote'), [
                'item_code' => 'ECH-ONLY-01',
                'quantity' => 50,
                'requested_period' => 1,
                'idempotency_key' => 'HTTP-PLN-1',
                'order_reference' => 'SO-HTTP-001',
                'as_of_date' => '2026-11-14',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'on_time')
            ->assertJsonPath('promised_period', 1)
            ->assertJsonPath('late_periods', 0)
            ->assertJsonPath('atp_cumulative', 150);

        $promiseCode = (string) $response->json('promise_code');

        // Conflict returns 409
        $this->actingAs($planner)
            ->postJson(route('control_tower.promises.quote'), [
                'item_code' => 'ECH-ONLY-01',
                'quantity' => 99,
                'requested_period' => 1,
                'idempotency_key' => 'HTTP-PLN-1',
                'order_reference' => 'SO-HTTP-001',
                'as_of_date' => '2026-11-14',
            ])
            ->assertStatus(409);

        // CSV export for auditor
        $csvResponse = $this->actingAs($auditor)
            ->get(route('control_tower.promises.export'));

        $csvResponse->assertOk();
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('promise_code,order_reference,item_code', $csvContent);
        $this->assertStringContainsString($promiseCode, $csvContent);
        $this->assertStringContainsString('ECH-ONLY-01', $csvContent);

        // Existing GET /control-tower/promises still returns Janji Pesanan Konsumen
        $this->actingAs($planner)
            ->get(route('control_tower.promises'))
            ->assertOk()
            ->assertSee('Janji Pesanan Konsumen');
    }
}
