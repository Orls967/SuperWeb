<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * Pengumpul ringkasan aktivitas shift gudang (putaway, pick, cycle count, transfer) untuk serah-terima shift.
 */
final class WmsShiftHandoverSummaryService
{
    /**
     * @return array{
     *     warehouse_id: string|int,
     *     open_tasks_count: int,
     *     exception_tasks_count: int,
     *     pending_receipts_count: int,
     *     active_reservations_count: int,
     *     pending_replenishments_count: int
     * }
     */
    public function buildHandover(string|int $warehouseId): array
    {
        $openTasks = (int) DB::table('wms_tasks')
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', ['created', 'assigned', 'in_progress', 'open'])
            ->count();

        $exceptionTasks = (int) DB::table('wms_tasks')
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'exception')
            ->count();

        $pendingReceipts = (int) DB::table('wms_receiving_receipts')
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'pending_putaway')
            ->count();

        $activeReservations = (int) DB::table('wms_stock_reservations')
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->count();

        $pendingReplenishments = (int) DB::table('wms_replenishments')
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'pending')
            ->count();

        return [
            'warehouse_id' => $warehouseId,
            'open_tasks_count' => $openTasks,
            'exception_tasks_count' => $exceptionTasks,
            'pending_receipts_count' => $pendingReceipts,
            'active_reservations_count' => $activeReservations,
            'pending_replenishments_count' => $pendingReplenishments,
        ];
    }
}
