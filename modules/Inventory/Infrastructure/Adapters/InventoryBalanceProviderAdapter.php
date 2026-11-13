<?php

declare(strict_types=1);

namespace Modules\Inventory\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Contracts\InventoryBalanceProvider;

/**
 * Implementasi aditif InventoryBalanceProvider berbasis tabel products.stock.
 */
final class InventoryBalanceProviderAdapter implements InventoryBalanceProvider
{
    public function getProductStockBalance(int $productId): int
    {
        $qty = DB::table('inv_stock_movements')->where('product_id', $productId)->sum('qty');

        return (int) $qty;
    }

    public function getAllProductBalances(): array
    {
        $rows = DB::table('inv_stock_movements')
            ->selectRaw('product_id, SUM(qty) as total_qty')
            ->groupBy('product_id')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->product_id] = (int) $row->total_qty;
        }

        return $out;
    }
}
