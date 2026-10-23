<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Shared\Application\BaseAction;

class EvaluateActuarialClaimFraudScoreAction extends BaseAction
{
    /**
     * Compute actuarial claim fraud risk score (0-100) and update claim workflow status.
     *
     * @return array{claim: InsuranceClaim, fraud_score: int, risk_decision: string, requires_investigation: bool}
     */
    public function handle(int $claimId): array
    {
        return $this->transaction(function () use ($claimId) {
            $claim = InsuranceClaim::where('id', $claimId)->lockForUpdate()->firstOrFail();
            $policy = InsurancePolicy::where('id', $claim->policy_id)->firstOrFail();

            $fraudScore = 0;

            // Factor 1: Rapid claim velocity (more than 2 claims in past 30 days)
            $recentClaimsCount = InsuranceClaim::where('policy_id', $policy->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
            if ($recentClaimsCount > 2) {
                $fraudScore += 40;
            }

            // Factor 2: Claim created within 48 hours of policy inception
            if ($policy->starts_at && $claim->created_at && abs($claim->created_at->diffInHours($policy->starts_at)) < 48) {
                $fraudScore += 30;
            }

            // Factor 3: Payout exceeds 85% of coverage limit
            if ($policy->coverage_limit_idr > 0 && ($claim->payout_amount_idr / $policy->coverage_limit_idr) > 0.85) {
                $fraudScore += 15;
            }

            $riskDecision = match (true) {
                $fraudScore >= 70 => 'SIU_FLAGGED',
                $fraudScore >= 30 => 'MANUAL_INVESTIGATION',
                default => 'AUTO_APPROVED',
            };

            $oldStatus = $claim->status;
            $claim->status = match ($riskDecision) {
                'SIU_FLAGGED' => 'siu_flagged',
                'MANUAL_INVESTIGATION' => 'manual_investigation',
                default => 'approved',
            };
            $claim->save();

            $this->audit(
                action: 'insurance.claim.fraud_evaluated',
                auditable: $claim,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => $claim->status,
                    'fraud_score' => $fraudScore,
                    'risk_decision' => $riskDecision,
                ],
                correlationId: "ins_fraud_{$claim->claim_number}",
                impactType: 'fraud_prevention'
            );

            return [
                'claim' => $claim,
                'fraud_score' => $fraudScore,
                'risk_decision' => $riskDecision,
                'requires_investigation' => $fraudScore >= 30,
            ];
        });
    }
}
