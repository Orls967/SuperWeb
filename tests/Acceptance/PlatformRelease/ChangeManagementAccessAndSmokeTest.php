<?php

declare(strict_types=1);

namespace Tests\Acceptance\PlatformRelease;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeManagementAccessAndSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_on_change_management_routes(): void
    {
        $response = $this->get('/platform/change');
        $response->assertRedirect('/login');

        $response = $this->get('/platform/release');
        $response->assertRedirect('/login');

        $response = $this->get('/platform/backfills');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_roles_receive_403_on_change_management(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get('/platform/change');
        $response->assertStatus(403);

        $response = $this->actingAs($customer)->get('/platform/release');
        $response->assertStatus(403);

        $response = $this->actingAs($customer)->get('/platform/backfills');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_change_management_and_release_dashboards(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/platform/change');
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get('/platform/release');
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get('/platform/backfills');
        $response->assertStatus(200);
    }
}
