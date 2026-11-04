<?php

declare(strict_types=1);

namespace Modules\ProServices\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProServicesHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_endpoint_accessible(): void
    {
        $user = User::factory()->create(['role' => 'project_manager']);
        $response = $this->actingAs($user)->get('/proservices/projects');
        $response->assertStatus(200);
    }
}
