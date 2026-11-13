<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use Modules\ControlTower\Domain\Algorithms\BlastRadiusImpactAnalyzer;
use Modules\ControlTower\Domain\Models\DisruptionAlert;

final class AnalyzeDisruptionBlastRadiusAction
{
    public function __construct(
        private readonly BlastRadiusImpactAnalyzer $analyzer,
    ) {}

    /**
     * @param  list<array{order_ref: string, item_code: string, echelon_path: list<string>, value_idr: int}>  $openOrders
     * @return array{
     *     alert: DisruptionAlert,
     *     impacted_orders: list<string>,
     *     affected_orders_count: int,
     *     revenue_at_risk_idr: int
     * }
     */
    public function execute(
        string $disruptedNode,
        string $category,
        string $title,
        array $openOrders,
    ): array {
        $impact = $this->analyzer->analyze($disruptedNode, $openOrders);
        $code = 'ALT-'.strtoupper(substr(hash('sha256', $disruptedNode.'|'.$category.'|'.(string) DisruptionAlert::count()), 0, 8));

        $alert = DisruptionAlert::create([
            'alert_code' => $code,
            'severity' => $impact['mitigation_priority'],
            'category' => strtoupper($category),
            'title' => $title,
            'description' => sprintf(
                'Node %s terdampak; %d pesanan berisiko dengan nilai IDR %d.',
                $disruptedNode,
                $impact['affected_orders_count'],
                $impact['revenue_at_risk_idr'],
            ),
            'affected_orders_count' => $impact['affected_orders_count'],
            'status' => 'active',
        ]);

        return [
            'alert' => $alert,
            'impacted_orders' => $impact['impacted_orders'],
            'affected_orders_count' => $impact['affected_orders_count'],
            'revenue_at_risk_idr' => $impact['revenue_at_risk_idr'],
        ];
    }
}
