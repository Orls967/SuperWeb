<?php

declare(strict_types=1);

namespace Modules\Agency\Application\Actions;

use Modules\Agency\Domain\Models\Agent;
use Modules\Agency\Domain\Models\CommissionAccrual;

class CalculateMultiTierAgentCommissionAction
{
    /**
     * Calculate multi-tier commissions for closing agent and upline parent agent.
     *
     * @return array{direct_accrual: CommissionAccrual, upline_accrual: ?CommissionAccrual, total_commission_idr: int}
     */
    public function execute(
        Agent $agent,
        string $referenceId,
        int $orderValueIdr,
        float $directRatePercent = 8.0,
        float $uplineOverridePercent = 2.0,
        int $holdDays = 14
    ): array {
        $holdUntil = now()->addDays($holdDays)->toDateString();

        // 1. Direct commission
        $directAmountIdr = (int) round(($orderValueIdr * $directRatePercent) / 100.0);
        $directAccrual = CommissionAccrual::create([
            'agent_id' => (string) $agent->id,
            'reference_id' => $referenceId,
            'source_type' => 'sales_order',
            'accrued_at' => now()->toDateString(),
            'base_amount_idr' => $orderValueIdr,
            'rate_percent' => $directRatePercent,
            'amount_idr' => $directAmountIdr,
            'status' => 'hold',
            'hold_until' => $holdUntil,
            'note' => sprintf('Komisi langsung tingkat 1 (%.1f%%) ditahan selama masa retur %d hari', $directRatePercent, $holdDays),
        ]);

        // 2. Upline override commission
        $uplineAccrual = null;
        $totalCommissionIdr = $directAmountIdr;

        if ($agent->parent_id) {
            $uplineAmountIdr = (int) round(($orderValueIdr * $uplineOverridePercent) / 100.0);
            $uplineAccrual = CommissionAccrual::create([
                'agent_id' => (string) $agent->parent_id,
                'reference_id' => $referenceId,
                'source_type' => 'upline_override',
                'accrued_at' => now()->toDateString(),
                'base_amount_idr' => $orderValueIdr,
                'rate_percent' => $uplineOverridePercent,
                'amount_idr' => $uplineAmountIdr,
                'status' => 'hold',
                'hold_until' => $holdUntil,
                'note' => sprintf('Komisi override upline tingkat 2 (%.1f%%) untuk agen binaan %s', $uplineOverridePercent, $agent->name),
            ]);

            $totalCommissionIdr += $uplineAmountIdr;
        }

        return [
            'direct_accrual' => $directAccrual,
            'upline_accrual' => $uplineAccrual,
            'total_commission_idr' => $totalCommissionIdr,
        ];
    }
}
