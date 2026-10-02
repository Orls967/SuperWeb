<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Queries;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentException;
use Modules\Logistics\Domain\Models\Truck;

class ControlTowerQuery
{
    /**
     * @return array<string, mixed>
     */
    public function get(?string $period = null): array
    {
        $period = $period ?? now()->format('Y-m');
        $periodStart = "{$period}-01";
        $periodEnd = Carbon::parse($periodStart)->addMonth()->toDateString();

        // KPI: OTIF (On-Time In-Full)
        $totalDelivered = Shipment::where('status', 'delivered')
            ->where('delivered_at', '>=', $periodStart)
            ->where('delivered_at', '<', $periodEnd)
            ->count();

        $totalShipments = Shipment::where('booked_at', '>=', $periodStart)
            ->where('booked_at', '<', $periodEnd)
            ->count();

        $otifRate = $totalShipments > 0 ? round(($totalDelivered / $totalShipments) * 100, 1) : 0;

        // Status distribution
        $statusChart = Shipment::select('status', DB::raw('COUNT(*) as total'))
            ->where('booked_at', '>=', $periodStart)
            ->where('booked_at', '<', $periodEnd)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // Fleet summary
        $fleetSummary = Truck::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // Average dwell time at hubs (hours)
        $avgDwell = DB::table('lgx_tracking_events')
            ->selectRaw("AVG(CASE WHEN event_type = 'DEPARTED_HUB' THEN CAST(strftime('%s', occurred_at) AS INTEGER) END - CASE WHEN event_type = 'ARRIVED_HUB' THEN CAST(strftime('%s', occurred_at) AS INTEGER) END) / 3600.0 as avg_hours")
            ->where('event_type', 'DEPARTED_HUB')
            ->value('avg_hours') ?? 0;

        // COD outstanding and settled
        $codOutstanding = (int) DB::table('lgx_cod_collections')
            ->whereNull('settled_at')
            ->sum('amount_collected_idr');
        $codSettled = (int) DB::table('lgx_cod_collections')
            ->whereNotNull('settled_at')
            ->sum('amount_collected_idr');

        // Margin per top lanes
        $topLanes = DB::table('lgx_shipments as s')
            ->join('lgx_locations as o', 's.origin_location_id', '=', 'o.id')
            ->join('lgx_locations as d', 's.destination_location_id', '=', 'd.id')
            ->select(
                DB::raw("o.name || ' → ' || d.name as lane"),
                DB::raw('COUNT(*) as shipments'),
                DB::raw('SUM(s.total_amount_idr) as revenue'),
            )
            ->where('s.status', 'delivered')
            ->groupBy('lane')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // Active exceptions count
        $activeExceptions = ShipmentException::whereNull('resolved_at')->count();

        return [
            'period' => $period,
            'otifRate' => $otifRate,
            'totalDelivered' => $totalDelivered,
            'totalShipments' => $totalShipments,
            'statusChart' => $statusChart,
            'fleetSummary' => $fleetSummary,
            'avgDwell' => $avgDwell,
            'codOutstanding' => $codOutstanding,
            'codSettled' => $codSettled,
            'topLanes' => $topLanes,
            'activeExceptions' => $activeExceptions,
        ];
    }
}
