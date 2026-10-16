<?php

declare(strict_types=1);

namespace Modules\Asset\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetHttpAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_asset_index(): void
    {
        $response = $this->get(route('asset.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_role_is_forbidden_from_asset_index(): void
    {
        $user = User::factory()->create(['role' => 'driver', 'email_verified_at' => now()]);
        $response = $this->actingAs($user)->get(route('asset.index'));
        $response->assertForbidden();
    }

    public function test_asset_manager_can_access_asset_index(): void
    {
        $user = User::factory()->create(['role' => 'asset_manager', 'email_verified_at' => now()]);
        $response = $this->actingAs($user)->get(route('asset.index'));
        $response->assertOk();
    }

    public function test_admin_can_access_asset_create(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $response = $this->actingAs($admin)->get(route('asset.create'));
        $response->assertOk();
    }
}
