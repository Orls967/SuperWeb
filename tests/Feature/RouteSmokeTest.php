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
}
