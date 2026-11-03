<?php

declare(strict_types=1);

namespace Tests\Feature\Food;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodBrandHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_manager_can_access_food_brands_and_create(): void
    {
        $user = User::factory()->create([
            'role' => 'brand_manager',
        ]);

        $response = $this->actingAs($user)->get(route('food.brands.index'));
        $response->assertStatus(200);

        $postResponse = $this->actingAs($user)->post(route('food.brands.store'), [
            'name' => 'Brand Nusantara Test',
            'code' => 'BRD-HTTP-001',
            'description' => 'Brand untuk uji HTTP modul Food',
        ]);
        $postResponse->assertRedirect(route('food.brands.index'));

        $this->assertDatabaseHas('food_brands', [
            'code' => 'BRD-HTTP-001',
            'name' => 'Brand Nusantara Test',
        ]);
    }

    public function test_unauthorized_user_is_forbidden_from_food_brands(): void
    {
        $guestUser = User::factory()->create([
            'role' => 'cashier',
        ]);

        $response = $this->actingAs($guestUser)->get(route('food.brands.index'));
        $response->assertStatus(403);
    }
}
