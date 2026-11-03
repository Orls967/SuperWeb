<?php

declare(strict_types=1);

namespace Tests\Feature\Fashion;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FashionCollectionHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_fashion_buyer_can_access_collections_and_store(): void
    {
        $user = User::factory()->create(['role' => 'fashion_buyer']);

        $response = $this->actingAs($user)->get(route('fashion.collections.index'));
        $response->assertStatus(200);

        $postResponse = $this->actingAs($user)->post(route('fashion.collections.store'), [
            'collection_code' => 'COL-HTTP-01',
            'name' => 'Koleksi Musim Gugur',
            'season' => 'FW26',
            'year' => 2026,
        ]);
        $postResponse->assertRedirect(route('fashion.collections.index'));

        $this->assertDatabaseHas('fsh_collections', [
            'collection_code' => 'COL-HTTP-01',
            'name' => 'Koleksi Musim Gugur',
        ]);
    }

    public function test_unauthorized_user_is_forbidden_from_fashion_collections(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($user)->get(route('fashion.collections.index'));
        $response->assertStatus(403);
    }
}
