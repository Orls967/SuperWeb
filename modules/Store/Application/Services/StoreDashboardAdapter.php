<?php

declare(strict_types=1);

namespace Modules\Store\Application\Services;

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
}
