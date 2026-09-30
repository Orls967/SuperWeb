<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Services;

use Modules\Mall\Contracts\TenantSalesProvider;
use Modules\Mall\Domain\Models\Lease;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Models\DailySummary;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\Outlet;

class RestoTenantSalesProvider implements TenantSalesProvider
{
    public function supports(Lease $lease): bool
    {
        $externalRef = $lease->tenant?->external_ref;
        if (! $externalRef) {
            return false;
        }

        return Outlet::query()->where('code', $externalRef)->exists();
    }

    public function getMonthlySales(Lease $lease, string $periodMonth): int
    {
        $externalRef = $lease->tenant?->external_ref;
        if (! $externalRef) {
            return 0;
        }

        $outlet = Outlet::query()->where('code', $externalRef)->first();
        if (! $outlet) {
            return 0;
        }

        [$year, $month] = explode('-', $periodMonth);

        $summarySales = (int) DailySummary::query()
            ->where('outlet_id', $outlet->id)
            ->whereYear('date', (int) $year)
            ->whereMonth('date', (int) $month)
            ->sum('net_sales');

        $orderSales = (int) Order::query()
            ->where('outlet_id', $outlet->id)
            ->whereIn('status', [OrderStatus::PAID])
            ->whereYear('paid_at', (int) $year)
            ->whereMonth('paid_at', (int) $month)
            ->selectRaw('COALESCE(SUM(subtotal - discount), 0) as total')
            ->value('total');

        return max($summarySales, $orderSales);
    }

    public function getTransactionCount(Lease $lease, string $periodMonth): int
    {
        $externalRef = $lease->tenant?->external_ref;
        if (! $externalRef) {
            return 0;
        }

        $outlet = Outlet::query()->where('code', $externalRef)->first();
        if (! $outlet) {
            return 0;
        }

        [$year, $month] = explode('-', $periodMonth);

        $summaryTx = (int) DailySummary::query()
            ->where('outlet_id', $outlet->id)
            ->whereYear('date', (int) $year)
            ->whereMonth('date', (int) $month)
            ->sum('transactions');

        $orderTx = (int) Order::query()
            ->where('outlet_id', $outlet->id)
            ->whereIn('status', [OrderStatus::PAID])
            ->whereYear('paid_at', (int) $year)
            ->whereMonth('paid_at', (int) $month)
            ->count();

        return max($summaryTx, $orderTx);
    }
}
