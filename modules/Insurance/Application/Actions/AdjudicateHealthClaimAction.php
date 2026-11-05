<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\GuaranteeLetter;
use Modules\Insurance\Domain\Models\HealthClaim;
use Modules\Insurance\Domain\Models\HealthPolicy;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

final class AdjudicateHealthClaimAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly IdempotentRequestGuard $guard,
    ) {}

    public function execute(
        string $claimNumber,
        string $policyNumber,
        string $claimType,
        int $incurredAmountIdr,
        ?string $glNumber,
        string $actorId,
        string $idempotencyKey,
    ): HealthClaim {
        $policy = HealthPolicy::where('policy_number', $policyNumber)->firstOrFail();
        $guaranteeLetter = $glNumber ? GuaranteeLetter::where('gl_number', $glNumber)->first() : null;

        return $this->guard->run(
            key: $idempotencyKey,
            scope: 'ins:health:claim:'.$claimNumber,
            payload: [
                'claim_number' => $claimNumber,
                'policy_number' => $policyNumber,
                'incurred_idr' => $incurredAmountIdr,
                'gl_number' => $glNumber,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use ($policy, $claimNumber, $claimType, $incurredAmountIdr, $guaranteeLetter) {
                return DB::transaction(function () use ($policy, $claimNumber, $claimType, $incurredAmountIdr, $guaranteeLetter) {
                    $remainingAnnualLimit = max(0, $policy->annual_limit_idr - $policy->annual_utilized_idr);
                    $deductible = $policy->deductible_idr;

                    $claimable = max(0, $incurredAmountIdr - $deductible);
                    $approvedAmount = min($claimable, $remainingAnnualLimit);
                    $patientShare = $incurredAmountIdr - $approvedAmount;

                    // Posting pembayaran klaim kesehatan ke ledger
                    $lines = Posting::lines();
                    $lines->debit('ins:claim_expense:IDR', $approvedAmount);
                    $lines->credit('ins:claim_clearing:IDR', $approvedAmount);

                    $tx = $this->ledger->post(new PostingDTO(
                        type: InsuranceTransactionTypes::INSURANCE_CLAIM_PAYOUT->value,
                        description: "Health Claim Settlement {$claimNumber} Policy {$policy->policy_number}",
                        idempotencyKey: 'clm:hlth:'.$claimNumber,
                        entries: $lines->toEntries('IDR'),
                        referenceType: 'ins_lh_health_claim',
                        referenceId: $claimNumber,
                    ));

                    $claim = HealthClaim::create([
                        'claim_number' => $claimNumber,
                        'health_policy_id' => $policy->id,
                        'guarantee_letter_id' => $guaranteeLetter?->id,
                        'claim_type' => $claimType,
                        'incurred_amount_idr' => $incurredAmountIdr,
                        'approved_amount_idr' => $approvedAmount,
                        'patient_share_idr' => $patientShare,
                        'status' => 'settled',
                        'adjudication_notes' => "Approved Rp {$approvedAmount}, Co-pay / Patient Share Rp {$patientShare}",
                        'settled_ledger_id' => $tx->id,
                    ]);

                    $policy->increment('annual_utilized_idr', $approvedAmount);
                    $guaranteeLetter?->update(['status' => 'settled']);

                    return $claim;
                });
            }
        );
    }
}
