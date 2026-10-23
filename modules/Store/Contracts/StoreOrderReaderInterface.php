<?php

declare(strict_types=1);

namespace Modules\Store\Contracts;

use Illuminate\Support\Collection;

interface StoreOrderReaderInterface
{
    /**
     * @return Collection<int, object>
     */
    public function getOrderItemsWithProduct(int $orderId): Collection;

    public function getProductPriceBySku(string $sku): ?int;

    public function getSalesAggregateBySku(string $sku): ?object;
}
