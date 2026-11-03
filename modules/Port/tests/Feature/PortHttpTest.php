<?php

declare(strict_types=1);

namespace Modules\Port\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_port_operator_can_access_port_routes(): void
    {
        $user = User::factory()->create(['role' => 'port_operator']);

        $response = $this->actingAs($user)->get('/port');
        $response->assertStatus(200);

        $responseBerth = $this->actingAs($user)->get('/port/berths');
        $responseBerth->assertStatus(200);

        $responseYard = $this->actingAs($user)->get('/port/yard');
        $responseYard->assertStatus(200);

        $responseBilling = $this->actingAs($user)->get('/port/billing');
        $responseBilling->assertStatus(200);
    }
}
