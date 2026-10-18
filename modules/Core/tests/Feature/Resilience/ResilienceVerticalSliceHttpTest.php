<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature\Resilience;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Domain\Models\CoreDataSovereigntyPolicy;
use Tests\TestCase;

class ResilienceVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_resilience_governance_routes_and_is_redirected(): void
    {
        $response = $this->get('/admin/resilience-governance');
        $response->assertRedirect('/login');
    }

    public function test_non_admin_user_receives_403_forbidden_on_resilience_governance(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)->get('/admin/resilience-governance');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_resilience_governance_dashboard_with_200_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/resilience-governance');
        $response->assertStatus(200);
        $response->assertSee('Resiliensi Global, DR Multi-Region & Data Sovereignty');
        $response->assertSee('ap-southeast-3');
        $response->assertSee('ap-southeast-1');
    }

    public function test_admin_can_execute_dr_drill_via_post_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/resilience-governance/execute-drill', [
            'drill_type' => 'FAILOVER_SIMULATION',
            'primary_region' => 'ap-southeast-3',
            'target_dr_region' => 'ap-southeast-1',
            'simulated_lag_seconds' => 12,
        ]);

        $response->assertRedirect('/admin/resilience-governance');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('core_dr_drill_executions', [
            'drill_type' => 'FAILOVER_SIMULATION',
            'primary_region' => 'ap-southeast-3',
            'target_dr_region' => 'ap-southeast-1',
            'status' => 'SUCCESS',
        ]);
    }

    public function test_admin_can_rotate_kms_key_via_post_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/resilience-governance/rotate-kms', [
            'key_alias' => 'master-database-envelope-key',
            'encryption_algorithm' => 'AES-256-GCM',
            'rotation_reason' => 'Emergency compliance key rotation',
        ]);

        $response->assertRedirect('/admin/resilience-governance');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('core_kms_key_rotations', [
            'key_alias' => 'master-database-envelope-key',
            'key_version' => 1,
            'status' => 'ACTIVE',
            'encryption_algorithm' => 'AES-256-GCM',
        ]);
    }

    public function test_admin_can_evaluate_data_sovereignty_residency_via_post_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        CoreDataSovereigntyPolicy::create([
            'jurisdiction_code' => 'ID',
            'regulation_reference' => 'UU PDP No. 27/2022',
            'mandatory_storage_region' => 'ap-southeast-3',
            'pii_encryption_required' => true,
            'cross_border_transfer_allowed' => false,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($admin)->post('/admin/resilience-governance/check-residency', [
            'jurisdiction_code' => 'ID',
            'data_type' => 'CUSTOMER_FINANCIAL_PROFILE',
            'storage_region' => 'ap-southeast-3',
            'contains_pii' => '1',
            'is_cross_border_transfer' => '0',
        ]);

        $response->assertRedirect('/admin/resilience-governance');
        $response->assertSessionHas('residency_result');

        $result = session('residency_result');
        $this->assertTrue($result['allowed']);
    }
}
