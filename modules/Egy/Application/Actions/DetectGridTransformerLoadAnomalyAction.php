<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Modules\Egy\Domain\Models\GridNode;
use Modules\Shared\Application\BaseAction;

class DetectGridTransformerLoadAnomalyAction extends BaseAction
{
    /**
     * @return array{node_code: string, is_anomaly: bool, severity: string, load_ratio_percent: int}
     */
    public function execute(string $nodeId): array
    {
        $node = GridNode::findOrFail($nodeId);

        $capacity = max(0.1, (float) $node->peak_capacity_mw);
        $currentLoad = (float) $node->current_load_mw;

        $loadRatioPercent = (int) round(($currentLoad / $capacity) * 100);

        $isAnomaly = false;
        $severity = 'NORMAL';

        if ($loadRatioPercent > 95) {
            $isAnomaly = true;
            $severity = 'CRITICAL_OVERLOAD';
        } elseif ($loadRatioPercent > 85) {
            $isAnomaly = true;
            $severity = 'WARNING_HIGH_LOAD';
        } elseif ($loadRatioPercent < 5) {
            $isAnomaly = true;
            $severity = 'FAULT_LOW_FEED';
        }

        $this->audit(
            action: 'egy_grid_anomaly_scanned',
            auditable: $node,
            newValues: ['load_ratio' => $loadRatioPercent, 'severity' => $severity],
            impactType: 'grid_stability',
        );

        return [
            'node_code' => $node->node_code,
            'is_anomaly' => $isAnomaly,
            'severity' => $severity,
            'load_ratio_percent' => $loadRatioPercent,
        ];
    }
}
