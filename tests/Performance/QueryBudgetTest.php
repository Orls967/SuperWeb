<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Models\Shipment;
use Tests\TestCase;

class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->customer = User::where('role', 'customer')->firstOrFail();
    }

    /**
     * Helper to profile queries for a callable request.
     */
    protected function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return count($queries);
    }

    public function test_main_dashboard_query_budget(): void
    {
        $count = $this->countQueries(function () {
            $response = $this->actingAs($this->admin)->get(route('dashboard'));
            $response->assertOk();
        });

        $this->assertLessThanOrEqual(25, $count, "Dashboard utama melebihi kuota query (tercatat: {$count})");
    }

    public function test_autodex_catalog_query_budget(): void
    {
        $count = $this->countQueries(function () {
            $response = $this->actingAs($this->customer)->get(route('autodex.index'));
            $response->assertOk();
        });

        $this->assertLessThanOrEqual(15, $count, "Katalog AutoDex melebihi kuota query (tercatat: {$count})");
    }

    public function test_store_catalog_query_budget(): void
    {
        $count = $this->countQueries(function () {
            $response = $this->actingAs($this->customer)->get(route('store.catalog.index'));
            $response->assertOk();
        });

        $this->assertLessThanOrEqual(15, $count, "Katalog Store melebihi kuota query (tercatat: {$count})");
    }

    public function test_resto_pos_query_budget(): void
    {
        $cashier = User::where('email', 'kasir@autoserve.test')->first() ?? $this->admin;

        $count = $this->countQueries(function () use ($cashier) {
            $response = $this->actingAs($cashier)->get(route('resto.pos.index'));
            $response->assertOk();
        });

        $this->assertLessThanOrEqual(20, $count, "Resto POS melebihi kuota query (tercatat: {$count})");
    }

    public function test_mall_site_plan_query_budget(): void
    {
        $count = $this->countQueries(function () {
            $response = $this->actingAs($this->admin)->get(route('mall.site-plan.index'));
            $response->assertOk();
        });

        $this->assertLessThanOrEqual(20, $count, "Denah Mall (Site Plan) melebihi kuota query (tercatat: {$count})");
    }

    public function test_mall_billing_query_budget(): void
    {
        $count = $this->countQueries(function () {
            $response = $this->actingAs($this->admin)->get(route('mall.billing.index'));
            $response->assertOk();
        });

        $this->assertLessThanOrEqual(25, $count, "Billing Mall melebihi kuota query (tercatat: {$count})");
    }

    public function test_mall_parking_query_budget(): void
    {
        $count = $this->countQueries(function () {
            $response = $this->actingAs($this->admin)->get(route('mall.parking.index'));
            $response->assertOk();
        });

        $this->assertLessThanOrEqual(20, $count, "Parkir Mall melebihi kuota query (tercatat: {$count})");
    }

    public function test_consolidated_group_dashboard_query_budget(): void
    {
        $count = $this->countQueries(function () {
            $response = $this->actingAs($this->admin)->get(route('admin.group-dashboard'));
            $response->assertOk();
        });

        $this->assertLessThanOrEqual(15, $count, "Dashboard Grup Konsolidasi melebihi kuota query (tercatat: {$count})");
    }

    public function test_global_search_api_query_budget(): void
    {
        $count = $this->countQueries(function () {
            $response = $this->actingAs($this->customer)->getJson(route('api.global-search', ['q' => 'Sari']));
            $response->assertOk();
        });

        $this->assertLessThanOrEqual(15, $count, "API Global Search melebihi kuota query (tercatat: {$count})");
    }

    public function test_logistics_tracking_lookup_query_budget(): void
    {
        $shipment = Shipment::first();
        if (! $shipment) {
            $this->markTestSkipped('No shipment available.');
        }

        $count = $this->countQueries(function () use ($shipment) {
            $startTime = microtime(true);
            $response = $this->get(route('track.show', ['tracking_number' => $shipment->tracking_number]));
            $response->assertOk();
            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $this->assertLessThan(100, $elapsedMs, "Lookup resi melebihi batas waktu (tercatat: {$elapsedMs}ms)");
        });

        $this->assertLessThanOrEqual(3, $count, "Lookup resi publik melebihi kuota query <= 3 (tercatat: {$count})");
    }

    public function test_logistics_dispatcher_board_query_budget(): void
    {
        $dispatcher = User::where('role', 'dispatcher')->first() ?? $this->admin;

        $count = $this->countQueries(function () use ($dispatcher) {
            $response = $this->actingAs($dispatcher)->get(route('logistics.dispatch.index'));
            $response->assertOk();
        });

        $this->assertLessThanOrEqual(10, $count, "Papan Dispatcher logistik melebihi kuota query <= 10 (tercatat: {$count})");
    }

    public function test_logistics_control_tower_query_budget(): void
    {
        $count = $this->countQueries(function () {
            $response = $this->actingAs($this->admin)->get(route('logistics.control-tower.index'));
            $response->assertOk();
        });

        $this->assertLessThanOrEqual(12, $count, "Control Tower logistik melebihi kuota query <= 12 (tercatat: {$count})");
    }

    public function test_logistics_accrue_demurrage_execution_time(): void
    {
        $startTime = microtime(true);
        $exitCode = Artisan::call('lgx:accrue-dd');
        $duration = microtime(true) - $startTime;

        $this->assertSame(0, $exitCode);
        $this->assertLessThan(30.0, $duration, "Command lgx:accrue-dd berjalan lebih dari 30 detik (tercatat: {$duration}s)");
    }
}
