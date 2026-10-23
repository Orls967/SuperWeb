<?php

declare(strict_types=1);

namespace Modules\Partner\Application\Actions;

use InvalidArgumentException;
use Modules\Partner\Domain\Models\JointPlan;

class SettlePartnerMilestoneCoDevAction
{
    /**
     * Settle joint co-development milestone and release milestone budget payout.
     *
     * @param  array<string, mixed>  $verifiedKpis
     * @return array{joint_plan: JointPlan, released_payout_idr: int, status: string}
     */
    public function execute(
        JointPlan $jointPlan,
        string $milestoneName,
        int $milestonePayoutIdr,
        array $verifiedKpis = []
    ): array {
        if ($jointPlan->status === 'completed' || $jointPlan->status === 'cancelled') {
            throw new InvalidArgumentException(
                sprintf('Joint Plan %s sudah dalam status %s dan tidak dapat dicairkan.', (string) $jointPlan->id, $jointPlan->status)
            );
        }

        if ($milestonePayoutIdr > (int) $jointPlan->budget_idr) {
            throw new InvalidArgumentException('Nilai pencairan milestone melebihi alokasi anggaran Joint Plan.');
        }

        $existingKpis = $jointPlan->kpi_targets ?? [];
        $existingKpis[$milestoneName] = array_merge($verifiedKpis, [
            'settled_at' => now()->toIso8601String(),
            'payout_idr' => $milestonePayoutIdr,
            'is_settled' => true,
        ]);

        $jointPlan->update([
            'kpi_targets' => $existingKpis,
            'status' => 'milestone_settled',
        ]);

        return [
            'joint_plan' => $jointPlan->fresh(),
            'released_payout_idr' => $milestonePayoutIdr,
            'status' => 'milestone_settled',
        ];
    }
}
