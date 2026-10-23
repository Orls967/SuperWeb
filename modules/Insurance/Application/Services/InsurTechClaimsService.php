<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Illuminate\Support\Facades\DB;
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
            $policyNumber = 'POL-'.strtoupper(substr(hash('sha256', "pol:{$product->id}:{$userId}:{$refType}:{$refId}:{$now->timestamp}"), 0, 10));

            $policy = InsurancePolicy::create([
                'policy_number' => $policyNumber,
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

            // Deduct premium: debit user wallet, credit insurance revenue reserve
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
            $claimNumber = 'CLM-'.strtoupper(substr(hash('sha256', "clm:{$policy->id}:{$triggerEventId}"), 0, 10));

            $claim = InsuranceClaim::create([
                'claim_number' => $claimNumber,
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

    /**
     * Four-eyes approval for held claims (DoR 72.4).
     */
    public function approveHeldClaim(InsuranceClaim $claim, int $approverId): InsuranceClaim
    {
        if ($claim->status !== 'pending_review') {
            return $claim;
        }

        return DB::transaction(function () use ($claim, $approverId) {
            $policy = $claim->policy;
            $claim->status = 'paid';
            $claim->paid_at = $this->simClock->now();
            $claim->save();

            if ($policy) {
                $policy->status = 'claimed';
                $policy->save();
            }

            $payoutAmount = $claim->payout_amount_idr;
            $userId = $policy ? $policy->user_id : $approverId;

            $this->ledgerService->post(new PostingDTO(
                type: 'insurance_claim_payout',
                description: "Approved Manual Claim Payout for {$claim->claim_number}",
                idempotencyKey: "ins:payout:approved:{$claim->id}",
                entries: [
                    PostingEntryDTO::forCode('ins:premium_reserve:IDR', 'IDR', -$payoutAmount),
                    PostingEntryDTO::forCode("wallet:user:{$userId}:IDR", 'IDR', $payoutAmount),
                ],
                referenceType: 'ins_claim',
                referenceId: (string) $claim->id,
            ));

            return $claim;
        });
    }

    /**
     * PSAK 62 unearned premium calculation (DoR 72.3).
     */
    public function calculateUnearnedPremium(InsurancePolicy $policy): int
    {
        $now = $this->simClock->now();
        if ($now->greaterThanOrEqualTo($policy->expires_at)) {
            return 0;
        }

        if ($now->lessThanOrEqualTo($policy->starts_at)) {
            return $policy->premium_paid_idr;
        }

        $totalSeconds = max(1, $policy->expires_at->timestamp - $policy->starts_at->timestamp);
        $remainingSeconds = max(0, $policy->expires_at->timestamp - $now->timestamp);

        return (int) round(($policy->premium_paid_idr * $remainingSeconds) / $totalSeconds);
    }

    /**
     * Mark policy lapsed upon payment failure (DoR 72.5 / 72.7d).
     */
    public function handleFailedPremiumPayment(InsurancePolicy $policy): InsurancePolicy
    {
        $policy->status = 'lapsed';
        $policy->save();

        return $policy;
    }

    /**
     * Reconcile insurance reserve against paid claims liability (DoR 72.7b, e).
     */
    public function reconcileReserve(int $initialReserveIdr = 0): array
    {
        $totalPaidClaims = (int) InsuranceClaim::where('status', 'paid')->sum('payout_amount_idr');
        $totalPremiumsCollected = (int) InsurancePolicy::sum('premium_paid_idr');
        $netReserve = $initialReserveIdr + $totalPremiumsCollected - $totalPaidClaims;

        return [
            'initial_reserve_idr' => $initialReserveIdr,
            'total_premiums_collected_idr' => $totalPremiumsCollected,
            'total_paid_claims_idr' => $totalPaidClaims,
            'net_reserve_idr' => $netReserve,
            'is_solvent' => $netReserve >= 0,
        ];
    }

    /**
     * Two-source insurance claims and policy audit.
     * Cross-checks policy premiums against product definitions and claim payouts.
     */
    public function auditInsurance(): array
    {
        $productCount = InsuranceProduct::count();
        $policyCount = InsurancePolicy::count();
        $claimCount = InsuranceClaim::count();
        $discrepancies = 0;

        $policies = InsurancePolicy::with('product')->get();
        foreach ($policies as $policy) {
            if ($policy->product && $policy->premium_paid_idr !== $policy->product->premium_amount_idr) {
                $discrepancies++;
            }
        }

        $paidClaims = InsuranceClaim::with('policy')->where('status', 'paid')->get();
        foreach ($paidClaims as $claim) {
            if ($claim->policy && $claim->payout_amount_idr !== $claim->policy->coverage_limit_idr) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'product_count' => $productCount,
            'policy_count' => $policyCount,
            'claim_count' => $claimCount,
            'discrepancies' => $discrepancies,
        ];
    }
}
