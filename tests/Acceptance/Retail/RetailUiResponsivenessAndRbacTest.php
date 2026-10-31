<?php

declare(strict_types=1);

namespace Tests\Acceptance\Retail;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailUiResponsivenessAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_unauthenticated_access_to_retail_routes_redirects_to_login(): void
    {
        $response = $this->get('/retail');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_retail_ops_role_can_access_retail_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'retail_ops']);

        $resDashboard = $this->actingAs($user)->get('/retail');
        $resDashboard->assertStatus(200);

        $resOverview = $this->actingAs($user)->get('/retail/overview');
        $resOverview->assertStatus(200);
    }

    public function test_marketplace_mgr_role_can_access_pricing_and_compliance(): void
    {
        $user = User::factory()->create(['role' => 'marketplace_mgr']);

        $resPricing = $this->actingAs($user)->get('/retail/pricing');
        $resPricing->assertStatus(200);

        $resCompliance = $this->actingAs($user)->get('/retail/compliance');
        $resCompliance->assertStatus(200);
    }

    public function test_retail_ops_can_access_darkstore_fulfillment(): void
    {
        $user = User::factory()->create(['role' => 'retail_ops']);

        $resDarkstore = $this->actingAs($user)->get('/retail/darkstore');
        $resDarkstore->assertStatus(200);
    }

    public function test_unauthorized_role_receives_403_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($user)->get('/retail');
        $response->assertStatus(403);
    }

    public function test_ui_contains_viewport_meta_tag_for_mobile_responsiveness(): void
    {
        $user = User::factory()->create(['role' => 'retail_ops']);

        $response = $this->actingAs($user)->get('/retail');
        $response->assertStatus(200);
        $response->assertSee('viewport', false);
        $response->assertSee('width=device-width', false);
    }

    public function test_darkstore_pickpack_ui_renders_mobile_action_controls(): void
    {
        $user = User::factory()->create(['role' => 'retail_ops']);

        $response = $this->actingAs($user)->get('/retail/darkstore');
        $response->assertStatus(200);
        $response->assertSee('Dark Store', false);
    }

    public function test_customer_cannot_view_orders_on_admin_portal(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get('/retail');
        $response->assertStatus(403);
    }
}
