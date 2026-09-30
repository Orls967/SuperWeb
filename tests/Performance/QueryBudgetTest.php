<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
