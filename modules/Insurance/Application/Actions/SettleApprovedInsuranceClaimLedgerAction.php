<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Shared\Application\BaseAction;

class SettleApprovedInsuranceClaimLedgerAction extends BaseAction
{
    /**
     * Settle an approved insurance claim and post claim indemnity payout.
     *
     * @return array{claim: InsuranceClaim, payout_amount_idr: int, status: string, paid_at: string}
     */
    public function handle(int $claimId): array
    {
        return $this->transaction(function () use ($claimId) {
            $claim = InsuranceClaim::where('id', $claimId)->lockForUpdate()->firstOrFail();

            if ($claim->status !== 'approved') {
                throw new \DomainException("Claim {$claim->claim_number} cannot be settled: status is '{$claim->status}', expected 'approved'.");
            }

            $claim->status = 'paid';
            $claim->paid_at = now();
            $claim->save();

            $idempotencyKey = hash('sha256', "ins:claim:settle:{$claimId}:{$claim->payout_amount_idr}");

            $this->audit(
                action: 'insurance.claim.settled',
                auditable: $claim,
                oldValues: ['status' => 'approved', 'paid_at' => null],
                newValues: [
                    'status' => 'paid',
                    'payout_amount_idr' => $claim->payout_amount_idr,
                    'paid_at' => $claim->paid_at->toIso8601String(),
                ],
                correlationId: "ins_settle_{$claim->claim_number}",
                impactType: 'claims_indemnity_payout'
            );

            $this->outbox(
                eventType: 'InsuranceClaimSettled',
                payload: [
                    'claim_id' => $claim->id,
                    'claim_number' => $claim->claim_number,
                    'policy_id' => $claim->policy_id,
                    'payout_amount_idr' => $claim->payout_amount_idr,
                    'paid_at' => $claim->paid_at->toIso8601String(),
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'claim' => $claim,
                'payout_amount_idr' => $claim->payout_amount_idr,
                'status' => 'paid',
                'paid_at' => $claim->paid_at->toIso8601String(),
            ];
        });
    }
}
