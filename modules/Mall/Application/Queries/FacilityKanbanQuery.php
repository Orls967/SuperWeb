<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Mall\Domain\Enums\AssetStatus;
use Modules\Mall\Domain\Enums\WorkOrderStatus;
use Modules\Mall\Domain\Models\Asset;
use Modules\Mall\Domain\Models\WorkOrder;

class FacilityKanbanQuery
{
    /**
     * @return array{
     *     kanban: array{
     *         open: Collection,
     *         in_progress: Collection,
     *         on_hold: Collection,
     *         completed: Collection
     *     },
     *     asset_stats: array{
     *         total: int,
     *         operational: int,
     *         maintenance: int,
     *         broken: int,
     *         pm_due: int
     *     },
     *     sla_breaches_count: int
     * }
     */
    public function get(): array
    {
        $orders = WorkOrder::with(['asset', 'tenant', 'unit', 'assignedTo'])
            ->latest('id')
            ->get();

        // Check SLA breaches on open/in_progress orders
        foreach ($orders as $order) {
            $order->checkSlaBreach();
        }

        $kanban = [
            'open' => $orders->where('status', WorkOrderStatus::OPEN)->values(),
            'in_progress' => $orders->where('status', WorkOrderStatus::IN_PROGRESS)->values(),
            'on_hold' => $orders->where('status', WorkOrderStatus::ON_HOLD)->values(),
            'completed' => $orders->where('status', WorkOrderStatus::COMPLETED)->values(),
        ];

        $assets = Asset::all();
        $pmDueCount = $assets->filter(fn ($a) => $a->isPmDue())->count();

        return [
            'kanban' => $kanban,
            'asset_stats' => [
                'total' => $assets->count(),
                'operational' => $assets->where('status', AssetStatus::OPERATIONAL)->count(),
                'maintenance' => $assets->where('status', AssetStatus::MAINTENANCE)->count(),
                'broken' => $assets->where('status', AssetStatus::BROKEN)->count(),
                'pm_due' => $pmDueCount,
            ],
            'sla_breaches_count' => $orders->where('sla_breached', true)->count(),
        ];
    }
}
