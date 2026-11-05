<?php

declare(strict_types=1);

namespace Modules\Forest\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ForestHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_plots_endpoint_accessible(): void
    {
        $user = User::factory()->create(['role' => 'forester']);

        $response = $this->actingAs($user)->get('/forest/plots');
        $response->assertOk();
    }
}
