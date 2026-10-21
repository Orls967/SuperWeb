<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

class PrescriptiveOperationsService
{
    /**
     * @return array{recommendation_code: string, action_type: string, confidence_bps: int, decision: string}
     */
    public function evaluatePrescriptiveAction(
        string $actionType,
        int $confidenceBps, // e.g. 9200 = 92%
        int $estimatedValueIdr,
        int $autoApprovalLimitIdr = 50000000
    ): array {
        $recommendationCode = 'REC-'.strtoupper($actionType).'-'.hash('crc32b', $actionType.$confidenceBps.$estimatedValueIdr);

        $decision = match (true) {
            $confidenceBps >= 9000 && $estimatedValueIdr <= $autoApprovalLimitIdr => 'AUTONOMOUS_EXECUTED',
            default => 'MANAGERIAL_APPROVAL_REQUIRED',
        };

        return [
            'recommendation_code' => $recommendationCode,
            'action_type' => $actionType,
            'confidence_bps' => $confidenceBps,
            'decision' => $decision,
        ];
    }
}
