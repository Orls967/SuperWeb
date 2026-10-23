<?php

declare(strict_types=1);

namespace Modules\Store\Contracts;

interface StoreProductStockPort
{
    public function getCachedStock(int $productId): int;

    public function reserveStock(int $productId, int $qty): void;

    public function releaseStock(int $productId, int $qty): void;

    public function adjustStock(int $productId, int $qty): void;
}
