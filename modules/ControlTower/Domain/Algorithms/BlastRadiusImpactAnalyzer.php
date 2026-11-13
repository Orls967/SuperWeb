<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Algorithms;

final class BlastRadiusImpactAnalyzer
{
    /**
     * @param  list<array{order_ref: string, item_code: string, echelon_path: list<string>, value_idr: int}>  $openOrders
     * @return array{
     *     impacted_orders: list<string>,
     *     affected_orders_count: int,
     *     revenue_at_risk_idr: int,
     *     mitigation_priority: string
     * }
     */
    public function analyze(string $disruptedNode, array $openOrders): array
    {
        $impacted = [];
        $revenueAtRiskIdr = 0;

        foreach ($openOrders as $order) {
            if (in_array($disruptedNode, $order['echelon_path'], true)) {
                $impacted[] = $order['order_ref'];
                $revenueAtRiskIdr += max(0, $order['value_idr']);
            }
        }

        sort($impacted);
        $count = count($impacted);

        $priority = match (true) {
            $count >= 10 || $revenueAtRiskIdr >= 500_000_000 => 'CRITICAL',
            $count >= 3 || $revenueAtRiskIdr >= 100_000_000 => 'HIGH',
            $count >= 1 => 'MEDIUM',
            default => 'LOW',
        };

        return [
            'impacted_orders' => $impacted,
            'affected_orders_count' => $count,
            'revenue_at_risk_idr' => $revenueAtRiskIdr,
            'mitigation_priority' => $priority,
        ];
    }
}
