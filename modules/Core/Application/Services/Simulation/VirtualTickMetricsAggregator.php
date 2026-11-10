<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

/**
 * Agregator metrik harian hasil eksekusi tick handler lintas modul.
 */
final class VirtualTickMetricsAggregator
{
    /**
     * @param  array<string, array<string, mixed>>  $handlerOutputs
     * @return array{
     *     total_revenue_accrued: float,
     *     total_discharges: int,
     *     total_dwells_checked: int,
     *     total_handlers_reporting: int,
     *     custom_kpis: array<string, mixed>
     * }
     */
    public function aggregateDailyOutputs(array $handlerOutputs): array
    {
        $revenue = 0.0;
        $discharges = 0;
        $dwells = 0;
        $custom = [];

        foreach ($handlerOutputs as $hName => $out) {
            if (isset($out['total_room_revenue_idr'])) {
                $revenue += (float) $out['total_room_revenue_idr'];
            }
            if (isset($out['total_billed_idr'])) {
                $revenue += (float) $out['total_billed_idr'];
            }
            if (isset($out['discharged_count'])) {
                $discharges += (int) $out['discharged_count'];
            }
            if (isset($out['total_active_dwells'])) {
                $dwells += (int) $out['total_active_dwells'];
            }

            $custom[$hName] = $out['status'] ?? 'unknown';
        }

        return [
            'total_revenue_accrued' => $revenue,
            'total_discharges' => $discharges,
            'total_dwells_checked' => $dwells,
            'total_handlers_reporting' => count($handlerOutputs),
            'custom_kpis' => $custom,
        ];
    }
}
