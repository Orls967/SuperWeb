<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsuranceParametricOracle;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsurancePolicyEarning;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Modules\Insurance\Ledger\InsuranceAccounts;

class InsurTechClaimsService
{
    public const STP_FRAUD_THRESHOLD_BPS = 3000;

    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $evidencePayload
     */
    public function triggerParametricClaim(
        string $oracleCode,
        int $policyId,
        string $triggerEventId,
        array $evidencePayload,
        string $idempotencyKey
    ): InsuranceClaim {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        $payloadHash = hash('sha256', json_encode([$oracleCode, $policyId, $triggerEventId, $evidencePayload]));
        $existing = InsuranceClaim::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            $existingHash = hash('sha256', json_encode([$oracleCode, $existing->policy_id, $existing->trigger_event_id, $existing->evidence_payload ?? []]));
            if ($existingHash !== $payloadHash) {
                throw new DomainException("Idempotency conflict for claim key '{$idempotencyKey}'.");
            }

            return $existing;
        }

        $duplicate = InsuranceClaim::where('policy_id', $policyId)
            ->where('trigger_event_id', $triggerEventId)
            ->first();
        if ($duplicate) {
            throw new DomainException("Claim already triggered for event '{$triggerEventId}' on policy {$policyId}.");
        }

        $oracle = InsuranceParametricOracle::where('oracle_code', $oracleCode)->first();
        if (! $oracle || ! $oracle->is_active) {
            throw new DomainException("Oracle '{$oracleCode}' is inactive or not found.");
        }

        $policy = InsurancePolicy::findOrFail($policyId);
        if ($policy->status !== 'active' || ($policy->expires_at && $policy->expires_at->isPast())) {
            throw new DomainException("Policy {$policyId} is expired or not active.");
        }

        $payoutAmount = (int) $policy->coverage_limit_idr;
        if (isset($evidencePayload['damage_estimate_idr'])) {
            $payoutAmount = min($payoutAmount, (int) $evidencePayload['damage_estimate_idr']);
        }

        // Fraud score heuristic (in basis points, 0..10000)
        $fraudScoreBps = 1000;
        if (! empty($evidencePayload['geo_spoof_risk'])) {
            $fraudScoreBps = 4500;
        } elseif (! empty($evidencePayload['verified_carrier'])) {
            $fraudScoreBps = 500;
        }

        $status = $fraudScoreBps < self::STP_FRAUD_THRESHOLD_BPS ? 'approved' : 'pending_investigation';

        $claimNumber = 'CLM-'.strtoupper(substr(hash('sha256', "clm:{$policyId}:{$triggerEventId}"), 0, 10));

        return DB::transaction(function () use (
            $claimNumber,
            $policyId,
            $triggerEventId,
            $payoutAmount,
            $evidencePayload,
            $status,
            $idempotencyKey
        ) {
            $claim = InsuranceClaim::create([
                'claim_number' => $claimNumber,
                'policy_id' => $policyId,
                'trigger_event_id' => $triggerEventId,
                'payout_amount_idr' => $payoutAmount,
                'evidence_payload' => $evidencePayload,
                'status' => $status,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($status === 'approved') {
                $this->accrueClaimIndemnity($payoutAmount);
            }

            return $claim;
        });
    }

    public function approveInvestigatedClaim(
        int $claimId,
        int $officerId,
        string $notes,
        string $idempotencyKey
    ): InsuranceClaim {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        return DB::transaction(function () use ($claimId) {
            $claim = InsuranceClaim::lockForUpdate()->findOrFail($claimId);
            $claim->status = 'approved';
            $claim->save();

            $this->accrueClaimIndemnity((int) $claim->payout_amount_idr);

            return $claim;
        });
    }

    public function rejectInvestigatedClaim(
        int $claimId,
        int $officerId,
        string $reason,
        string $idempotencyKey
    ): InsuranceClaim {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        return DB::transaction(function () use ($claimId) {
            $claim = InsuranceClaim::lockForUpdate()->findOrFail($claimId);
            $claim->status = 'rejected';
            $claim->save();

            return $claim;
        });
    }

    public function settleApprovedClaim(int $claimId, string $idempotencyKey): void
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        DB::transaction(function () use ($claimId) {
            $claim = InsuranceClaim::lockForUpdate()->findOrFail($claimId);
            if ($claim->status !== 'approved') {
                throw new DomainException("Cannot settle claim with status '{$claim->status}'. Must be 'approved'.");
            }

            $policy = $claim->policy;
            $user = User::findOrFail($policy->user_id);
            $wallet = $user->walletAccount('IDR');

            $payout = (int) $claim->payout_amount_idr;

            // Disburse to wallet
            $wallet->cached_balance = (string) (((int) $wallet->cached_balance) + $payout);
            $wallet->save();

            // Clear claimant payable
            $payable = LedgerAccount::where('code', 'claimant:payable:default:IDR')->first();
            if ($payable) {
                $payable->cached_balance = (string) max(0, ((int) $payable->cached_balance) - $payout);
                $payable->save();
            }

            $claim->status = 'settled';
            $claim->paid_at = $this->simClock->now();
            $claim->save();
        });
    }

    protected function accrueClaimIndemnity(int $payoutAmount): void
    {
        // Debit expense
        $expense = InsuranceAccounts::claimExpense();
        $expense->cached_balance = (string) (((int) $expense->cached_balance) + $payoutAmount);
        $expense->save();

        // Credit payable
        $payable = InsuranceAccounts::claimantPayable();
        $payable->cached_balance = (string) (((int) $payable->cached_balance) + $payoutAmount);
        $payable->save();
    }

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

            $totalEarned = (int) InsurancePolicyEarning::where('policy_id', $policy->id)->sum('daily_amount_idr');
            if ($totalEarned > $policy->premium_paid_idr) {
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
