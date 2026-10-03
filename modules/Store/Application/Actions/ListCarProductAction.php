<?php

declare(strict_types=1);

namespace Modules\Store\Application\Actions;

use Illuminate\Support\Str;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Models\Product;

class ListCarProductAction extends BaseAction
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function execute(
        Car $car,
        ?int $price = null,
        int $stock = 1,
        ?int $categoryId = null,
        ?string $description = null
    ): Product {
        return $this->transaction(function () use ($car, $price, $stock, $categoryId, $description) {
            $brandName = $car->brand?->name ?? 'Mobil';
            $name = "{$brandName} {$car->model}";
            $carPrice = $price ?? (int) ($car->price_idr ?: 250000000);

            /** @var Product|null $existingProduct */
            $existingProduct = Product::query()
                ->where('productable_type', 'dex_car')
                ->where('productable_id', $car->id)
                ->lockForUpdate()
                ->first();

            $product = $existingProduct ?? new Product([
                'productable_type' => 'dex_car',
                'productable_id' => $car->id,
            ]);

            $isNew = ! $product->exists;

            $product->fill([
                'uuid' => $product->uuid ?: (string) Str::uuid(),
                'category_id' => $categoryId,
                'sku' => $product->sku ?: ('CAR-'.str_pad((string) $car->id, 4, '0', STR_PAD_LEFT)),
                'name' => $name,
                'slug' => $product->slug ?: Str::slug("{$name}-{$car->year_start}-".Str::random(4)),
                'description' => $description ?? $car->description ?? "Unit {$name} resmi bergaransi.",
                'price' => $carPrice,
                'compare_at_price' => (int) ($carPrice * 1.05),
                'cached_stock' => $isNew ? $stock : ($product->cached_stock + $stock),
                'is_listed' => true,
                'is_car' => true,
                'weight_gram' => 1500000, // 1500 kg
                'images' => $car->image_url ? [$car->image_url] : ['https://images.unsplash.com/photo-1617788138017-80ad40651399?auto=format&fit=crop&w=800&q=80'],
            ]);

            $product->save();

            // Record stock movement
            StockMovement::create([
                'product_id' => $product->id,
                'qty' => $stock,
                'reason' => StockMovementReason::INITIAL,
                'source_type' => 'dex_car',
                'source_id' => $car->id,
                'note' => "Listing mobil {$name} ke Store",
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            return $product;
        });
    }
}
