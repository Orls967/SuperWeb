<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_simulation_governance_routes_and_is_redirected(): void
    {
        $response = $this->get('/admin/simulation-governance');
        $response->assertRedirect('/login');
    }

    public function test_non_admin_user_receives_403_forbidden_on_simulation_governance(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)->get('/admin/simulation-governance');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_simulation_governance_dashboard_with_200_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/simulation-governance');
        $response->assertStatus(200);
        $response->assertSee('Simulation Kernel, Universal Event Spine & Digital Twin Bus');
        $response->assertSee('Virtual Clock');
        $response->assertSee('Universal Event Spine');
    }

    public function test_admin_can_advance_simulation_clock_via_post_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/simulation-governance/advance', [
            'days' => 3,
            'run_name' => 'test_quarterly_run',
        ]);

        $response->assertRedirect('/admin/simulation-governance');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sim_runs', [
            'name' => 'test_quarterly_run',
            'last_completed_day' => 3,
        ]);
    }

    public function test_admin_can_publish_spine_event_via_post_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/simulation-governance/publish-spine', [
            'topic' => 'auto.vehicle_acquired',
            'event_name' => 'VehicleAcquiredSpineEvent',
            'payload' => json_encode(['vin' => 'VIN-SIM-999', 'plate' => 'B 1010 SIM']),
            'idempotency_key' => 'idemp_test_http_101',
        ]);

        $response->assertRedirect('/admin/simulation-governance');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sim_event_spine', [
            'topic' => 'auto.vehicle_acquired',
            'event_name' => 'VehicleAcquiredSpineEvent',
            'idempotency_key' => 'idemp_test_http_101',
        ]);
    }

    public function test_admin_can_record_digital_twin_state_via_post_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/simulation-governance/record-twin', [
            'entity_type' => 'vehicle',
            'entity_id' => 'TWIN-HTTP-001',
            'state' => json_encode(['odometer_km' => 25000, 'battery_soh' => 97]),
            'is_sandbox' => 0,
        ]);

        $response->assertRedirect('/admin/simulation-governance');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sim_twin_states', [
            'entity_type' => 'vehicle',
            'entity_id' => 'TWIN-HTTP-001',
            'is_sandbox' => false,
        ]);
    }

    public function test_admin_can_update_scale_seeder_checkpoint_via_post_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/simulation-governance/scale-provision', [
            'seeder_name' => 'UniverseScaleSeeder',
            'stage' => 'telematics_bulk_insert',
            'batch_number' => 25,
            'completed' => 1,
        ]);

        $response->assertRedirect('/admin/simulation-governance');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sim_seeder_checkpoints', [
            'seeder_name' => 'UniverseScaleSeeder',
            'stage' => 'telematics_bulk_insert',
            'batch_number' => 25,
            'completed' => true,
        ]);
    }
}
