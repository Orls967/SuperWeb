<?php

declare(strict_types=1);

namespace Modules\Inventory\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

class InventoryMovementExplorerHttpTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(int $stock = 20): Product
    {
        return Product::create([
            'uuid' => (string) Str::uuid(),
            'sku' => 'TEST-'.Str::random(6),
            'name' => 'Busi Iridium Racing',
            'slug' => 'busi-iridium-'.Str::random(6),
            'description' => 'Busi pengapian presisi',
            'price' => 75000,
            'cached_stock' => $stock,
            'is_listed' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('inventory.explorer.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_movement_explorer_index(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(50);

        StockMovement::create([
            'product_id' => $product->id,
            'qty' => 50,
            'reason' => StockMovementReason::PURCHASE,
            'note' => 'Initial bulk stock receiving',
            'created_by' => $user->id,
            'created_at' => Carbon::now(),
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'qty' => -5,
            'reason' => StockMovementReason::SALE,
            'note' => 'Online customer retail order',
            'created_by' => $user->id,
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($user)->get(route('inventory.explorer.index'));

        $response->assertOk();
        $response->assertSeeText('Inventory Stock Movement Explorer');
        $response->assertSeeText('PRD-'.$product->id);
        $response->assertSeeText('+50');
        $response->assertSeeText('-5');
    }

    public function test_filter_movements_by_reason(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(30);

        StockMovement::create([
            'product_id' => $product->id,
            'qty' => 30,
            'reason' => StockMovementReason::PURCHASE,
            'created_by' => $user->id,
            'created_at' => Carbon::now(),
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'qty' => -2,
            'reason' => StockMovementReason::WASTE,
            'created_by' => $user->id,
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($user)->get(route('inventory.explorer.index', [
            'reason' => StockMovementReason::WASTE->value,
        ]));

        $response->assertOk();
        $response->assertSeeText('-2');
        $response->assertDontSeeText('+30');
    }

    public function test_authenticated_user_can_inspect_single_movement(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(15);

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'qty' => 15,
            'reason' => StockMovementReason::ADJUSTMENT,
            'note' => 'Physical stock count discrepancy correction',
            'created_by' => $user->id,
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($user)->get(route('inventory.explorer.show', $movement->id));

        $response->assertOk();
        $response->assertSeeText("Stock Movement Detail #{$movement->id}");
        $response->assertSeeText('PRD-'.$product->id);
        $response->assertSeeText('+15 Units');
        $response->assertSeeText('Physical stock count discrepancy correction');
    }
}
