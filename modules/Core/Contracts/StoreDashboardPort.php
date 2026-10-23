<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Support\Collection;

interface StoreDashboardPort
{
    /**
     * @return array{total_orders: int, pending_orders: int}
     */
    public function getOrderStats(): array;

    public function getUserOrdersCount(int $userId): int;

    /**
     * @return Collection<int, mixed>
     */
    public function getUserRecentOrders(int $userId, int $limit = 5): Collection;
}
