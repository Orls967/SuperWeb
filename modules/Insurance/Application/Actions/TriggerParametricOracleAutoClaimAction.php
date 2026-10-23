<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Shared\Application\BaseAction;

class TriggerParametricOracleAutoClaimAction extends BaseAction
{
    /**
     * Trigger automated parametric insurance claim when oracle event condition is met.
     *
     * @param  array<string, mixed>  $evidencePayload
     * @return array{claim: InsuranceClaim, claim_number: string, payout_amount_idr: int, status: string}
     */
    public function handle(
        int $policyId,
        string $triggerEventId,
        int $claimAmountIdr,
        array $evidencePayload = []
    ): array {
        return $this->transaction(function () use (
            $policyId,
            $triggerEventId,
            $claimAmountIdr,
            $evidencePayload
        ) {
            $policy = InsurancePolicy::where('id', $policyId)->lockForUpdate()->firstOrFail();

            if ($policy->status !== 'active') {
                throw new \DomainException("Policy {$policy->policy_number} is not active (status: {$policy->status}).");
            }

            if (now()->gt($policy->expires_at)) {
                throw new \DomainException("Policy {$policy->policy_number} expired on {$policy->expires_at->toIso8601String()}.");
            }

            $payoutAmountIdr = min($claimAmountIdr, $policy->coverage_limit_idr);
            $claimNumber = 'CLM-'.date('Ymd').'-'.str_pad((string) $policyId, 4, '0', STR_PAD_LEFT).'-'.substr(hash('sha256', $triggerEventId), 0, 6);
            $idempotencyKey = hash('sha256', "ins:claim:param:{$policyId}:{$triggerEventId}");

            $claim = InsuranceClaim::firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'claim_number' => $claimNumber,
                    'policy_id' => $policyId,
                    'trigger_event_id' => $triggerEventId,
                    'payout_amount_idr' => $payoutAmountIdr,
                    'evidence_payload' => $evidencePayload,
                    'status' => 'pending_review',
                ]
            );

            $this->audit(
                action: 'insurance.parametric_claim.triggered',
                auditable: $claim,
                oldValues: null,
                newValues: [
                    'claim_number' => $claim->claim_number,
                    'policy_id' => $policyId,
                    'trigger_event_id' => $triggerEventId,
                    'payout_amount_idr' => $payoutAmountIdr,
                ],
                correlationId: "ins_clm_{$claim->claim_number}",
                impactType: 'parametric_trigger'
            );

            $this->outbox(
                eventType: 'ParametricInsuranceClaimTriggered',
                payload: [
                    'claim_id' => $claim->id,
                    'claim_number' => $claim->claim_number,
                    'policy_id' => $policyId,
                    'payout_amount_idr' => $payoutAmountIdr,
                    'trigger_event_id' => $triggerEventId,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'claim' => $claim,
                'claim_number' => $claim->claim_number,
                'payout_amount_idr' => $payoutAmountIdr,
                'status' => $claim->status,
            ];
        });
    }
}
