<?php

declare(strict_types=1);

namespace Modules\Circular\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CircularMarketplaceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_authorized_roles_can_access_circular_views(): void
    {
        $seller = User::factory()->create(['role' => 'material_seller']);
        $buyer = User::factory()->create(['role' => 'material_buyer']);
        $admin = User::factory()->create(['role' => 'circular_admin']);

        // 1. Seller access listings
        $response = $this->actingAs($seller)->get(route('circular.listings.index'));
        $response->assertOk();

        // 2. Buyer access market
        $response = $this->actingAs($buyer)->get(route('circular.market.index'));
        $response->assertOk();

        // 3. Admin access auctions
        $response = $this->actingAs($admin)->get(route('circular.auctions.index'));
        $response->assertOk();
    }
}
