<?php

declare(strict_types=1);

namespace Modules\Store\Application\Services;

use App\Models\User;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Store\Domain\Models\Cart;
use Modules\Store\Domain\Models\CartItem;
use Modules\Store\Domain\Models\Product;

class CartService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function getOrCreateCart(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    public function addItem(User $user, int $productId, int $qty = 1): CartItem
    {
        $cart = $this->getOrCreateCart($user);
        /** @var Product $product */
        $product = Product::findOrFail($productId);

        $existing = $cart->items()->where('product_id', $productId)->first();
        $targetQty = ($existing?->qty ?? 0) + $qty;

        $available = $this->inventoryService->available($productId);
        if ($available < $targetQty) {
            throw new InsufficientStockException(
                "Stok {$product->name} tidak mencukupi! Tersedia: {$available}, diminta: {$targetQty}"
            );
        }

        if ($existing) {
            $existing->update([
                'qty' => $targetQty,
                'price_snapshot' => $product->price,
            ]);

            return $existing->fresh();
        }

        return CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => $qty,
            'price_snapshot' => $product->price,
        ]);
    }

    public function updateItem(User $user, int $productId, int $qty): ?CartItem
    {
        $cart = $this->getOrCreateCart($user);

        if ($qty <= 0) {
            $this->removeItem($user, $productId);

            return null;
        }

        $product = Product::findOrFail($productId);
        $available = $this->inventoryService->available($productId);
        if ($available < $qty) {
            throw new InsufficientStockException(
                "Stok {$product->name} tidak mencukupi! Tersedia: {$available}, diminta: {$qty}"
            );
        }

        $item = $cart->items()->where('product_id', $productId)->firstOrFail();
        $item->update([
            'qty' => $qty,
            'price_snapshot' => $product->price,
        ]);

        return $item->fresh();
    }

    public function removeItem(User $user, int $productId): void
    {
        $cart = $this->getOrCreateCart($user);
        $cart->items()->where('product_id', $productId)->delete();
    }

    public function clearCart(User $user): void
    {
        $cart = $this->getOrCreateCart($user);
        $cart->items()->delete();
    }
}
