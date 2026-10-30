<?php

declare(strict_types=1);

namespace Tests\Acceptance\Energy;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnergyUiResponsivenessAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_unauthenticated_access_to_energy_routes_redirects_to_login(): void
    {
        $response = $this->get('/energy');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_energy_manager_role_can_access_energy_dashboard_and_meters(): void
    {
        $user = User::factory()->create(['role' => 'energy_manager']);

        $resDashboard = $this->actingAs($user)->get('/energy');
        $resDashboard->assertStatus(200);

        $resMeters = $this->actingAs($user)->get('/energy/meters');
        $resMeters->assertStatus(200);
    }

    public function test_grid_operator_role_can_access_dispatch_and_microgrid(): void
    {
        $user = User::factory()->create(['role' => 'grid_operator']);

        $resDispatch = $this->actingAs($user)->get('/energy/dispatch');
        $resDispatch->assertStatus(200);

        $resMicrogrid = $this->actingAs($user)->get('/energy/microgrid');
        $resMicrogrid->assertStatus(200);
    }

    public function test_unauthorized_role_receives_403_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($user)->get('/energy');
        $response->assertStatus(403);
    }

    public function test_ui_contains_viewport_meta_tag_for_mobile_responsiveness(): void
    {
        $user = User::factory()->create(['role' => 'energy_manager']);

        $response = $this->actingAs($user)->get('/energy');
        $response->assertStatus(200);
        $response->assertSee('viewport', false);
        $response->assertSee('width=device-width', false);
    }

    public function test_operator_ui_renders_emergency_controls_for_mobile(): void
    {
        $user = User::factory()->create(['role' => 'grid_operator']);

        $response = $this->actingAs($user)->get('/energy/microgrid');
        $response->assertStatus(200);
        $response->assertSee('Islanding', false);
    }
}
