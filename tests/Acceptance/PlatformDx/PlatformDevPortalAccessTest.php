<?php

declare(strict_types=1);

namespace Tests\Acceptance\PlatformDx;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformDevPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_dev_portal(): void
    {
        $response = $this->get('/platform/dev');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_developer_can_view_dev_portal_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'dev@typeshit.local']);

        $response = $this->actingAs($user)->get('/platform/dev');
        $response->assertStatus(200);
        $response->assertSee('Developer Experience & Platform Portal');
        $response->assertSee('Sandbox Environments');
        $response->assertSee('Quality Ratchet Metrics');
    }

    public function test_dev_portal_shows_service_slo_and_error_budget_cards(): void
    {
        $user = User::factory()->create(['email' => 'sre@typeshit.local']);

        $response = $this->actingAs($user)->get('/platform/dev/slos');
        $response->assertStatus(200);
        $response->assertSee('Service Level Objectives (SLO)');
        $response->assertSee('Error Budget Burn Rate');
    }
}
