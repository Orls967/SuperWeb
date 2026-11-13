<?php

declare(strict_types=1);

namespace Modules\Inventory\Contracts;

/**
 * Kontrak penyedia saldo stok Inventory untuk rekonsiliasi lintas modul (aditif).
 */
interface InventoryBalanceProvider
{
    /**
     * Saldo stok produk menurut subledger Inventory (integer base unit).
     */
    public function getProductStockBalance(int $productId): int;

    /**
     * Peta saldo stok seluruh produk aktif [product_id => qty_base].
     *
     * @return array<int, int>
     */
    public function getAllProductBalances(): array;
}
