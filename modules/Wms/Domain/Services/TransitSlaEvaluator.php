<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

/**
 * Evaluator keterlambatan pengiriman in-transit antar-gudang & ambang pencadangan transit loss.
 */
final class TransitSlaEvaluator
{
    /**
     * @return array{
     *     sla_status: string,
     *     hours_overdue: int,
     *     recommend_loss_claim: bool
     * }
     */
    public function evaluate(int $hoursInTransit, int $slaHours = 72, int $lossThresholdHours = 168): array
    {
        $overdue = max(0, $hoursInTransit - $slaHours);
        if ($hoursInTransit >= $lossThresholdHours) {
            return [
                'sla_status' => 'presumed_lost',
                'hours_overdue' => $overdue,
                'recommend_loss_claim' => true,
            ];
        }

        if ($hoursInTransit > $slaHours) {
            return [
                'sla_status' => 'overdue',
                'hours_overdue' => $overdue,
                'recommend_loss_claim' => false,
            ];
        }

        return [
            'sla_status' => 'on_time',
            'hours_overdue' => 0,
            'recommend_loss_claim' => false,
        ];
    }
}
