<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface StoreDashboardPort
{
    /**
     * @return array{total_orders: int, pending_orders: int}
     */
    public function getOrderStats(): array;
}
