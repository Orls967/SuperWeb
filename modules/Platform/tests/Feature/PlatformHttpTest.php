<?php

declare(strict_types=1);

namespace Modules\Platform\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/platform/dev');
        $response->assertRedirect('/login');
    }

    public function test_non_admin_gets_forbidden(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get('/platform/dev');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_dev_portal_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/platform/dev');
        $response->assertStatus(200);
    }

    public function test_admin_can_access_sandboxes_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/platform/dev/sandboxes');
        $response->assertStatus(200);
    }

    public function test_admin_can_access_slos_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/platform/dev/slos');
        $response->assertStatus(200);
    }

    public function test_admin_can_access_metrics_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/platform/dev/metrics');
        $response->assertStatus(200);
    }

    public function test_admin_can_create_sandbox_via_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/platform/dev/api/sandboxes', [
            'name' => 'sbx-http-feature-test',
            'tier' => 'standard',
            'ttl_hours' => 24,
            'dataset_preset' => 'minimal',
            'seed_profile' => 'default',
        ]);

        $response->assertStatus(201);
    }
}
