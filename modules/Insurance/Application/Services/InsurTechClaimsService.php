<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsuranceProduct;

class InsurTechClaimsService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    public function issueEmbeddedPolicy(
        InsuranceProduct $product,
        int $userId,
        string $refType,
        string $refId,
        int $durationDays = 7
    ): InsurancePolicy {
        return DB::transaction(function () use ($product, $userId, $refType, $refId, $durationDays) {
            $now = $this->simClock->now();

            $policy = InsurancePolicy::create([
                'policy_number' => 'POL-'.strtoupper(Str::random(8)),
                'product_id' => $product->id,
                'user_id' => $userId,
                'subject_ref_type' => $refType,
                'subject_ref_id' => $refId,
                'premium_paid_idr' => $product->premium_amount_idr,
                'coverage_limit_idr' => $product->max_payout_idr,
                'starts_at' => $now,
                'expires_at' => $now->copy()->addDays($durationDays),
                'status' => 'active',
            ]);

            // Deduct premium: debit user wallet, credit insurance revenue pool
            $this->ledgerService->post(new PostingDTO(
                type: 'insurance_premium',
                description: "Embedded Insurance Premium for {$product->product_code}",
                idempotencyKey: "ins:prem:{$policy->id}",
                entries: [
                    PostingEntryDTO::forCode("wallet:user:{$userId}:IDR", 'IDR', -$product->premium_amount_idr),
                    PostingEntryDTO::forCode('ins:premium_reserve:IDR', 'IDR', $product->premium_amount_idr),
                ],
                referenceType: 'ins_policy',
                referenceId: (string) $policy->id,
            ));

            return $policy;
        });
    }

    public function processEventAutopilotClaim(
        string $triggerEventType,
        string $refType,
        string $refId,
        string $triggerEventId,
        array $evidencePayload,
        int $fraudAnomalyScore = 10
    ): ?InsuranceClaim {
        $policy = InsurancePolicy::where('subject_ref_type', $refType)
            ->where('subject_ref_id', $refId)
            ->whereHas('product', function ($q) use ($triggerEventType) {
                $q->where('trigger_event_type', $triggerEventType);
            })
            ->first();

        if (! $policy) {
            return null;
        }

        $idempotencyKey = "ins:claim:{$policy->id}:{$triggerEventId}";
        $existing = InsuranceClaim::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        if ($policy->status !== 'active') {
            return null;
        }

        return DB::transaction(function () use ($policy, $triggerEventId, $evidencePayload, $fraudAnomalyScore, $idempotencyKey) {
            $existing = InsuranceClaim::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $payoutAmount = $policy->coverage_limit_idr;
            $status = ($fraudAnomalyScore > 80) ? 'pending_review' : 'paid';

            $claim = InsuranceClaim::create([
                'claim_number' => 'CLM-'.strtoupper(Str::random(8)),
                'policy_id' => $policy->id,
                'trigger_event_id' => $triggerEventId,
                'payout_amount_idr' => $payoutAmount,
                'evidence_payload' => $evidencePayload,
                'status' => $status,
                'paid_at' => ($status === 'paid') ? $this->simClock->now() : null,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($status === 'paid') {
                $policy->status = 'claimed';
                $policy->save();

                // Autopilot instant ledger payout
                $this->ledgerService->post(new PostingDTO(
                    type: 'insurance_claim_payout',
                    description: "Autopilot Claim Payout for Policy {$policy->policy_number}",
                    idempotencyKey: "ins:payout:{$claim->id}",
                    entries: [
                        PostingEntryDTO::forCode('ins:premium_reserve:IDR', 'IDR', -$payoutAmount),
                        PostingEntryDTO::forCode("wallet:user:{$policy->user_id}:IDR", 'IDR', $payoutAmount),
                    ],
                    referenceType: 'ins_claim',
                    referenceId: (string) $claim->id,
                ));
            }

            return $claim;
        });
    }
}
