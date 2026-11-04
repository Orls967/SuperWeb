<?php

declare(strict_types=1);

namespace Modules\Marine\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Domain\Models\MarinePond;
use Tests\TestCase;

class MarineHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_marine_web_index_accessible_for_authenticated_users(): void
    {
        $user = User::factory()->create(['role' => 'officer']);

        $response = $this->actingAs($user)->get('/marine');
        $response->assertStatus(200);
    }

    public function test_marine_pond_show_anti_idor(): void
    {
        $farmer1 = User::factory()->create(['role' => 'farmer']);
        $farmer2 = User::factory()->create(['role' => 'farmer']);

        $pond = MarinePond::create([
            'code' => 'POND-F1-01',
            'name' => 'Tambak Udang 1',
            'farmer_id' => $farmer1->id,
            'capacity_units' => 10000,
            'location' => 'Banyuwangi',
        ]);

        // Farmer 1 can view
        $this->actingAs($farmer1)->get("/marine/ponds/{$pond->id}")->assertOk();

        // Farmer 2 cannot view (anti-IDOR)
        $this->actingAs($farmer2)->get("/marine/ponds/{$pond->id}")->assertForbidden();
    }
}
