<?php

declare(strict_types=1);

namespace Modules\Store\Application\Services;

use Modules\Store\Contracts\StoreProductStockPort;
use Modules\Store\Domain\Models\Product;
use RuntimeException;

class StoreProductStockAdapter implements StoreProductStockPort
{
    public function getCachedStock(int $productId): int
    {
        return (int) (Product::where('id', $productId)->value('cached_stock') ?? 0);
    }

    public function reserveStock(int $productId, int $qty): void
    {
        /** @var Product $product */
        $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

        if ($product->cached_stock < $qty) {
            throw new RuntimeException(
                "Stok tidak mencukupi untuk {$product->name}. Tersedia: {$product->cached_stock}, diminta: {$qty}"
            );
        }

        $product->cached_stock -= $qty;
        $product->save();
    }

    public function releaseStock(int $productId, int $qty): void
    {
        /** @var Product $product */
        $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();
        $product->cached_stock += $qty;
        $product->save();
    }

    public function adjustStock(int $productId, int $qty): void
    {
        /** @var Product $product */
        $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

        $newStock = $product->cached_stock + $qty;
        if ($newStock < 0) {
            throw new RuntimeException(
                "Stok tidak mencukupi untuk {$product->name}. Tersedia: {$product->cached_stock}, perubahan: {$qty}"
            );
        }

        $product->cached_stock = $newStock;
        $product->save();
    }
}
