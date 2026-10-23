<?php

declare(strict_types=1);

namespace Modules\Store\Application\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Store\Contracts\StoreOrderReaderInterface;

class StoreOrderReaderService implements StoreOrderReaderInterface
{
    public function getOrderItemsWithProduct(int $orderId): Collection
    {
        return DB::table('store_order_items as oi')
            ->join('store_products as p', 'p.id', '=', 'oi.product_id')
            ->where('oi.order_id', $orderId)
            ->get([
                'oi.id',
                'oi.id as item_id',
                'oi.product_id',
                'p.sku',
                'oi.quantity',
                'oi.quantity as qty',
                'oi.unit_price',
                'oi.price_snapshot',
            ]);
    }

    public function getProductPriceBySku(string $sku): ?int
    {
        $price = DB::table('store_products')->where('sku', $sku)->value('price');

        return $price !== null ? (int) $price : null;
    }

    public function getSalesAggregateBySku(string $sku): ?object
    {
        return DB::table('store_order_items')
            ->join('store_products', 'store_products.id', '=', 'store_order_items.product_id')
            ->where('store_products.sku', $sku)
            ->selectRaw('COALESCE(SUM(qty),0) as sold, COALESCE(SUM(line_total),0) as revenue')
            ->first();
    }
}
