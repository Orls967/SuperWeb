<?php

declare(strict_types=1);

namespace Modules\Store\Application\Services;

use Illuminate\Support\Collection;
use Modules\Core\Contracts\StoreDashboardPort;
use Modules\Store\Domain\Models\Order;

class StoreDashboardAdapter implements StoreDashboardPort
{
    public function getOrderStats(): array
    {
        return [
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
        ];
    }

    public function getUserOrdersCount(int $userId): int
    {
        return Order::where('user_id', $userId)->count();
    }

    public function getUserRecentOrders(int $userId, int $limit = 5): Collection
    {
        return Order::where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get();
    }
}
