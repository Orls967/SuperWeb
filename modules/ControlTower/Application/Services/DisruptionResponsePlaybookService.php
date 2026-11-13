<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Application\Actions\AnalyzeDisruptionBlastRadiusAction;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Services\DisruptionSeverityClassifier;

final class DisruptionResponsePlaybookService
{
    public function __construct(
        private readonly AnalyzeDisruptionBlastRadiusAction $blastAction,
        private readonly DisruptionSeverityClassifier $classifier,
    ) {}

    /**
     * @param  list<array{order_ref: string, item_code: string, echelon_path: list<string>, value_idr: int}>  $openOrders
     * @return array<string, mixed>
     */
    public function raiseDisruptionWithBlastRadius(string $node, string $category, string $title, array $openOrders): array
    {
        $res = $this->blastAction->execute($node, $category, $title, $openOrders);
        $severity = $this->classifier->classify($res['affected_orders_count'], 5, $res['revenue_at_risk_idr']);

        if ($res['alert']->severity !== $severity) {
            $res['alert']->update(['severity' => $severity]);
        }

        return $res;
    }

    public function resolveAlert(DisruptionAlert $alert): DisruptionAlert
    {
        $alert->update(['status' => 'resolved']);

        return $alert->fresh() ?? $alert;
    }
}
