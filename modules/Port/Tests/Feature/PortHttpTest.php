<?php

declare(strict_types=1);

namespace Modules\Port\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_terminal_operator_can_access_port_routes(): void
    {
        $user = User::factory()->create(['role' => 'terminal_operator']);

        $response = $this->actingAs($user)->get('/port/vessel-visits');
        $response->assertStatus(200);

        $responseBerth = $this->actingAs($user)->get('/port/berth-plan');
        $responseBerth->assertStatus(200);
    }
}
