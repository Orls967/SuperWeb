<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;
use RuntimeException;

class UniversalCrossLineInsuranceClaimService
{
    public function __construct(
        protected Ledger $ledger
    ) {}

    /**
     * Universal parametric insurance claim trigger from 12 lines
     *
     * @return array{claim_id: string, status: string, payout_idr: int, policy_number: string}
     */
    public function executeParametricClaim(
        string $policyNumber,
        string $triggerTypeCode,
        int $severityScore,
        string $telemetryProofHash,
        string $claimantId
    ): array {
        $policy = InsurancePolicy::query()->where('policy_number', $policyNumber)->firstOrFail();

        if (strtoupper((string) $policy->status) !== 'ACTIVE') {
            throw new RuntimeException("Insurance policy {$policyNumber} is not active.");
        }

        // Calculate payout deterministically based on coverage terms (integer math)
        $payoutAmountIdr = min(
            $policy->coverage_limit_idr,
            (int) floor(($policy->coverage_limit_idr * $severityScore) / 100)
        );

        if ($payoutAmountIdr <= 0) {
            throw new RuntimeException('Calculated insurance payout must be greater than zero.');
        }

        $idempotencyKey = 'ins_claim:'.$policyNumber.':'.hash('sha256', $triggerTypeCode.$telemetryProofHash);

        // Double-entry: Debit Central Treasury Reserve Pool, Credit Claimant Payable
        $claimantAccount = 'claimant:payable:default:IDR';

        $this->ledger->post(new PostingDTO(
            type: InsuranceTransactionTypes::INSURANCE_CLAIM_PAYOUT->value,
            description: "Autopilot parametric claim [{$triggerTypeCode}] policy {$policyNumber}",
            idempotencyKey: $idempotencyKey,
            entries: [
                PostingEntryDTO::forCode('ins:reserve_pool:IDR', 'IDR', -$payoutAmountIdr),
                PostingEntryDTO::forCode($claimantAccount, 'IDR', $payoutAmountIdr),
            ],
            referenceType: 'insurance_policies',
            referenceId: (string) $policy->id,
        ));

        InsuranceClaim::query()->updateOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'claim_number' => 'CLM-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 10)),
                'policy_id' => $policy->id,
                'trigger_event_id' => $triggerTypeCode,
                'payout_amount_idr' => $payoutAmountIdr,
                'evidence_payload' => [
                    'trigger' => $triggerTypeCode,
                    'proof_hash' => $telemetryProofHash,
                    'severity' => $severityScore,
                    'claimant_id' => $claimantId,
                ],
                'status' => 'paid',
            ]
        );

        return [
            'claim_id' => $idempotencyKey,
            'status' => 'DISBURSED_AUTOPILOT',
            'payout_idr' => $payoutAmountIdr,
            'policy_number' => $policyNumber,
        ];
    }
}
