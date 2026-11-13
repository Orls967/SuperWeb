<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * Penilai konsistensi urutan tugas gudang & detektor anomali transisi wms_task_events.
 */
final class WmsTaskAuditTrailService
{
    /**
     * @return array{
     *     total_events: int,
     *     exception_events: int,
     *     cancelled_events: int,
     *     recent_events: list<object>
     * }
     */
    public function summarizeEvents(int $limit = 50): array
    {
        $total = (int) DB::table('wms_task_events')->count();
        $exceptions = (int) DB::table('wms_task_events')->where('to_status', 'exception')->count();
        $cancelled = (int) DB::table('wms_task_events')->where('to_status', 'cancelled')->count();

        $recent = DB::table('wms_task_events')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();

        return [
            'total_events' => $total,
            'exception_events' => $exceptions,
            'cancelled_events' => $cancelled,
            'recent_events' => $recent,
        ];
    }
}
