<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $mekanik;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->mekanik = User::where('role', 'mekanik')->firstOrFail();
        $this->customer = User::where('role', 'customer')->firstOrFail();
    }

    // ============================================================
    // 1. PUBLIC & GUEST ACCESS
    // ============================================================

    public function test_guest_accessible_routes(): void
    {
        $guestRoutes = [
            'login',
            'register',
            'mall.directory.index',
        ];

        foreach ($guestRoutes as $route) {
            $response = $this->get(route($route));
            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 302], true),
                "Route [{$route}] harus mengembalikan status 200 atau redirect untuk guest (diterima: {$response->getStatusCode()})"
            );
        }
    }

    public function test_unauthenticated_user_redirected_from_protected_routes(): void
    {
        $protectedRoutes = [
            'dashboard',
            'wallet.index',
            'resto.pos.index',
            'admin.group-dashboard',
            'mall.billing.index',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->get(route($route));
            $response->assertRedirect(route('login'));
        }
    }

    // ============================================================
    // 2. CUSTOMER ROLE ACCESS
    // ============================================================

    public function test_customer_can_access_customer_routes(): void
    {
        $customerRoutes = [
            'dashboard',
            'bookings.create',
            'autodex.index',
            'store.catalog.index',
            'store.cart.index',
            'wallet.index',
            'crypto.market.index',
            'crypto.portfolio.index',
            'notifications.index',
            'profile.edit',
        ];

        foreach ($customerRoutes as $route) {
            $response = $this->actingAs($this->customer)->get(route($route));
            $response->assertOk();
        }
    }

    public function test_customer_forbidden_from_admin_and_staff_routes(): void
    {
        $forbiddenRoutes = [
            'admin.group-dashboard',
            'store.admin.products.index',
            'services.index',
            'spareparts.index',
        ];

        foreach ($forbiddenRoutes as $route) {
            $response = $this->actingAs($this->customer)->get(route($route));
            $this->assertTrue(
                in_array($response->getStatusCode(), [403, 302], true),
                "Customer dilarang mengakses [{$route}] (diterima: {$response->getStatusCode()})"
            );
        }
    }

    // ============================================================
    // 3. MEKANIK ROLE ACCESS
    // ============================================================

    public function test_mekanik_can_access_dashboard_and_forbidden_from_admin_group(): void
    {
        $response = $this->actingAs($this->mekanik)->get(route('dashboard'));
        $response->assertOk();

        $forbidden = $this->actingAs($this->mekanik)->get(route('admin.group-dashboard'));
        $this->assertTrue(
            in_array($forbidden->getStatusCode(), [403, 302], true),
            "Mekanik dilarang mengakses admin group dashboard (diterima: {$forbidden->getStatusCode()})"
        );
    }

    // ============================================================
    // 4. ADMIN ROLE ACCESS (ALL 5 BUSINESS LINES)
    // ============================================================

    public function test_admin_can_access_all_module_dashboards_and_features(): void
    {
        $adminRoutes = [
            // Core & Platform
            'dashboard',
            'admin.group-dashboard',
            'admin.health.index',
            'wallet.index',
            'crypto.market.index',
            'crypto.portfolio.index',

            // Otomotif (AutoServe, AutoDex, Store)
            'services.index',
            'spareparts.index',
            'bookings.create',
            'autodex.index',
            'store.catalog.index',
            'store.admin.products.index',
            'store.admin.orders.index',

            // Kuliner (RM Sari Ranah)
            'resto.pos.index',
            'resto.kitchen.index',
            'resto.analytics.index',
            'resto.menu.index',
            'resto.ingredients.index',
            'resto.purchases.index',
            'resto.outlets.index',
            'resto.transfers.index',
            'resto.catering.index',
            'resto.deliveries.index',
            'resto.franchise.index',

            // Properti (Duta Mall)
            'mall.site-plan.index',
            'mall.tenants.index',
            'mall.leases.index',
            'mall.billing.index',
            'mall.utilities.index',
            'mall.parking.index',
            'mall.parking.gate.entry',
            'mall.parking.gate.exit',
            'mall.parking.footfall',
            'mall.parking.members',
            'mall.loyalty.index',
            'mall.events.index',
            'mall.facilities.index',

            // Logistik (Sari Ranah Express)
            'logistics.dashboard',
            'logistics.control-tower.index',
            'logistics.locations.index',
            'logistics.lanes.index',
            'logistics.fleet.index',
            'logistics.drivers.index',
            'logistics.shipments.index',
            'logistics.shipments.create',
            'logistics.dispatch.index',
            'logistics.hub.index',
            'logistics.carriers.index',
            'logistics.margins',
            'logistics.claims.index',
            'logistics.dd.index',
            'logistics.customs.index',
            'logistics.fuel.index',
            'logistics.cod.index',
            'logistics.exceptions.index',

            // Master Data (Party & Contract)
            'party.index',
            'party.legal-entities',
            'contract.index',
            'contract.clauses.index',
            'contract.templates.index',
            'asset.index',
            'asset.create',
            'asset.audit',
            'asset.depreciation.index',
            'supplier.index',
            'supplier.create',
            'supplier.portal.home',
            'procurement.dashboard',
            'agency.index',
            'partners.index',
        ];

        foreach ($adminRoutes as $route) {
            $response = $this->actingAs($this->admin)->get(route($route));
            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Admin harus dapat mengakses route [{$route}] (diterima: {$response->getStatusCode()})"
            );
        }
    }

    // ============================================================
    // 5. LOGISTICS ROLES ACCESS MATRIX
    // ============================================================

    public function test_logistics_roles_authorization_matrix(): void
    {
        $logisticsAdmin = User::where('role', 'logistics_admin')->first()
            ?? User::factory()->create(['role' => 'logistics_admin']);
        $dispatcher = User::where('role', 'dispatcher')->first()
            ?? User::factory()->create(['role' => 'dispatcher']);
        $driverUser = User::where('role', 'driver')->first()
            ?? User::factory()->create(['role' => 'driver']);
        $shipper = User::where('role', 'shipper')->first()
            ?? User::factory()->create(['role' => 'shipper']);

        // 1. Logistics Admin can access control tower and dispatch
        $this->actingAs($logisticsAdmin)->get(route('logistics.control-tower.index'))->assertOk();
        $this->actingAs($logisticsAdmin)->get(route('logistics.dispatch.index'))->assertOk();

        // 2. Dispatcher can access dispatch board but forbidden from control tower
        $this->actingAs($dispatcher)->get(route('logistics.dispatch.index'))->assertOk();
        $this->actingAs($dispatcher)->get(route('logistics.control-tower.index'))->assertForbidden();

        // 3. Driver can access driver tasks but forbidden from control tower and dispatch board
        $this->actingAs($driverUser)->get(route('logistics.driver.tasks'))->assertOk();
        $this->actingAs($driverUser)->get(route('logistics.control-tower.index'))->assertForbidden();
        $this->actingAs($driverUser)->get(route('logistics.dispatch.index'))->assertForbidden();

        // 4. Shipper can access shipments but forbidden from dispatch board and control tower
        $this->actingAs($shipper)->get(route('logistics.shipments.index'))->assertOk();
        $this->actingAs($shipper)->get(route('logistics.control-tower.index'))->assertForbidden();
        $this->actingAs($shipper)->get(route('logistics.dispatch.index'))->assertForbidden();

        // 5. Customer forbidden from logistics internal operations
        $this->actingAs($this->customer)->get(route('logistics.control-tower.index'))->assertForbidden();
        $this->actingAs($this->customer)->get(route('logistics.dispatch.index'))->assertForbidden();
    }

    // ============================================================
    // 6. CONTRACT & PARTY ROLES ACCESS MATRIX
    // ============================================================

    public function test_contract_and_party_roles_authorization_matrix(): void
    {
        $contractManager = User::where('role', 'contract_manager')->first()
            ?? User::factory()->create(['role' => 'contract_manager']);
        $legal = User::where('role', 'legal')->first()
            ?? User::factory()->create(['role' => 'legal']);
        $partyManager = User::where('role', 'party_manager')->first()
            ?? User::factory()->create(['role' => 'party_manager']);

        // 1. contract_manager: full manage on contracts
        $this->actingAs($contractManager)->get(route('contract.index'))->assertOk();
        $this->actingAs($contractManager)->get(route('contract.clauses.index'))->assertOk();
        $this->actingAs($contractManager)->get(route('contract.obligations'))->assertOk();

        // 2. legal: seluruh UI kontrak (guard grup /contracts memuat admin,contract_manager,legal)
        $this->actingAs($legal)->get(route('contract.index'))->assertOk();
        $this->actingAs($legal)->get(route('contract.obligations'))->assertOk();
        $this->actingAs($legal)->get(route('contract.clauses.index'))->assertOk();
        // ...tapi legal tidak masuk ke direktori pihak
        $this->actingAs($legal)->get(route('party.index'))->assertForbidden();

        // 3. party_manager: party directory yes, contract editor no
        $this->actingAs($partyManager)->get(route('party.index'))->assertOk();
        $this->actingAs($partyManager)->get(route('contract.index'))->assertForbidden();

        // 4. Customer forbidden from contract & party operations
        $this->actingAs($this->customer)->get(route('contract.index'))->assertForbidden();
        $this->actingAs($this->customer)->get(route('contract.obligations'))->assertForbidden();
        $this->actingAs($this->customer)->get(route('party.index'))->assertForbidden();
    }

    // ============================================================
    // 7. AGENCY ROLE ACCESS MATRIX
    // ============================================================

    public function test_agent_role_authorization_matrix(): void
    {
        $agent = User::where('role', 'agent')->first()
            ?? User::factory()->create(['role' => 'agent']);

        // Agent can access agency directory/portal
        $this->actingAs($agent)->get(route('agency.index'))->assertOk();

        // Customer forbidden from agency portal
        $this->actingAs($this->customer)->get(route('agency.index'))->assertForbidden();
    }
}
