<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use Modules\Wms\Application\Services\ReceivingToleranceEvaluator;

/**
 * Klasifikasi kode galat/deviasi penerimaan GRN & jalur eskalasi persetujuan.
 */
final class ReceivingDiscrepancyClassifier
{
    /**
     * @return array{
     *     severity: string,
     *     escalation_role: string,
     *     hold_in_quarantine: bool,
     *     action_code: string
     * }
     */
    public function classify(string $toleranceDecision, int $varianceBps, bool $hasDamagedPackaging = false): array
    {
        if ($hasDamagedPackaging) {
            return [
                'severity' => 'critical',
                'escalation_role' => 'auditor',
                'hold_in_quarantine' => true,
                'action_code' => 'QUARANTINE_DAMAGED_INBOUND',
            ];
        }

        if ($toleranceDecision === ReceivingToleranceEvaluator::DECISION_OVER_RECEIPT) {
            return [
                'severity' => abs($varianceBps) > 1500 ? 'high' : 'medium',
                'escalation_role' => 'admin',
                'hold_in_quarantine' => false,
                'action_code' => 'REQUIRE_SUPERVISOR_OVER_RECEIPT_APPROVAL',
            ];
        }

        if ($toleranceDecision === ReceivingToleranceEvaluator::DECISION_SHORT_RECEIPT) {
            return [
                'severity' => abs($varianceBps) > 1500 ? 'high' : 'medium',
                'escalation_role' => 'procurement',
                'hold_in_quarantine' => false,
                'action_code' => 'OPEN_VENDOR_SHORTAGE_CLAIM',
            ];
        }

        return [
            'severity' => 'low',
            'escalation_role' => 'hub_operator',
            'hold_in_quarantine' => false,
            'action_code' => 'PROCEED_TO_DIRECTED_PUTAWAY',
        ];
    }
}
