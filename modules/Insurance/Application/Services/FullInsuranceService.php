<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Insurance\Domain\Models\InsuranceActuarialTriangle;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Insurance\Domain\Models\InsurancePremiumSchedule;
use Modules\Insurance\Domain\Models\InsuranceProductPenuh;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;
use Modules\Shared\Domain\Algorithms\Actuarial\ChainLadder;

final class FullInsuranceService
{
    public const FOUR_EYES_THRESHOLD_IDR = 50000000; // Rp50.000.000

    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * Rating engine: deterministically calculates gross, ceded, and retained premiums.
     *
     * @param  array{product_id: int, customer_party_ref: int, sum_insured_idr: int, risk_multiplier_bps?: int, starts_at: string, expires_at: string, installments?: int}  $payload
     */
    public function quoteAndBindPolicy(array $payload, string $idempotencyKey): InsurancePolicyPenuh
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $product = InsuranceProductPenuh::findOrFail((int) $payload['product_id']);
        if (! $product->is_active) {
            throw new DomainException("Produk asuransi '{$product->product_code}' tidak aktif.");
        }

        $sumInsured = (int) $payload['sum_insured_idr'];
        if ($sumInsured <= 0) {
            throw new InvalidArgumentException('Uang pertanggungan (sum insured) harus bernilai positif.');
        }

        $riskMultiplierBps = (int) ($payload['risk_multiplier_bps'] ?? 10000); // 1.0x = 10000 bps
        if ($riskMultiplierBps < 5000 || $riskMultiplierBps > 50000) {
            throw new InvalidArgumentException('Faktor risiko berada di luar ambang wajar (0.5x - 5.0x).');
        }

        // Kalkulasi premi bruto integer tanpa IEEE float inaccuracy
        // gross = max(min_premium, (sumInsured * baseRateBps * riskMultiplierBps) / (10000 * 10000))
        $rawNumerator = $sumInsured * $product->base_rate_bps * $riskMultiplierBps;
        $calculatedPremium = (int) intdiv($rawNumerator, 100000000);
        $grossPremium = max((int) $product->min_premium_idr, $calculatedPremium);

        // Treaty quota share allocation (misal 40% ceded, 60% retained)
        $quotaSharePct = (int) $product->quota_share_pct;
        $cededPremium = (int) intdiv($grossPremium * $quotaSharePct, 100);
        $retainedPremium = $grossPremium - $cededPremium;

        return DB::transaction(function () use (
            $payload,
            $product,
            $sumInsured,
            $grossPremium,
            $cededPremium,
            $retainedPremium,
            $idempotencyKey
        ) {
            $policyCount = InsurancePolicyPenuh::count() + 1;
            $policyNumber = sprintf('POL-%s-%06d', $product->line_category, $policyCount);

            $policy = InsurancePolicyPenuh::create([
                'policy_number' => $policyNumber,
                'product_id' => $product->id,
                'customer_party_ref' => (int) $payload['customer_party_ref'],
                'sum_insured_idr' => $sumInsured,
                'gross_premium_idr' => $grossPremium,
                'ceded_premium_idr' => $cededPremium,
                'retained_premium_idr' => $retainedPremium,
                'starts_at' => $payload['starts_at'],
                'expires_at' => $payload['expires_at'],
                'status' => 'active',
            ]);

            // Buat jadwal premi (default 1 installment penuh atau berkala)
            $installments = max(1, (int) ($payload['installments'] ?? 1));
            $installmentAmount = (int) intdiv($grossPremium, $installments);
            $remainder = $grossPremium % $installments;

            for ($i = 1; $i <= $installments; $i++) {
                $amt = $installmentAmount + ($i === $installments ? $remainder : 0);
                InsurancePremiumSchedule::create([
                    'policy_id' => $policy->id,
                    'installment_number' => $i,
                    'due_date' => $payload['starts_at'],
                    'amount_idr' => $amt,
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            // Double-entry ledger booking:
            // 1. Piutang premi & liabilitas premi belum dihasilkan (UPR)
            // Debit ar:ins:premium:IDR / Kredit ins:unearned_premium:IDR
            // 2. Alokasi reasuransi ceded treaty
            // Debit reins:ceded_premium_expense:IDR / Kredit ar:ins:premium:IDR
            $this->ledgerService->post(new PostingDTO(
                type: InsuranceTransactionTypes::INSURANCE_PREMIUM->value,
                description: "Penerbitan Polis Asuransi {$policyNumber}",
                idempotencyKey: "ins:bind:{$policyNumber}:{$idempotencyKey}",
                entries: [
                    PostingEntryDTO::forCode('ar:ins:premium:IDR', 'IDR', $retainedPremium),
                    PostingEntryDTO::forCode('reins:ceded_premium_expense:IDR', 'IDR', $cededPremium),
                    PostingEntryDTO::forCode('ins:unearned_premium:IDR', 'IDR', -$grossPremium),
                ],
                referenceType: 'ins_policy_penuh',
                referenceId: (string) $policy->id,
            ));

            return $policy;
        });
    }

    /**
     * Submit a claim against a policy with duplicate prevention.
     *
     * @param  array{policy_id: int, incident_date: string, incident_description: string, claimed_amount_idr: int}  $payload
     */
    public function registerClaim(array $payload, string $idempotencyKey): InsuranceClaimPenuh
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $policy = InsurancePolicyPenuh::findOrFail((int) $payload['policy_id']);
        if ($policy->status !== 'active') {
            throw new DomainException("Polis '{$policy->policy_number}' tidak aktif untuk pengajuan klaim.");
        }

        $claimedAmount = (int) $payload['claimed_amount_idr'];
        if ($claimedAmount <= 0) {
            throw new InvalidArgumentException('Nilai klaim harus positif.');
        }
        if ($claimedAmount > $policy->sum_insured_idr) {
            throw new DomainException("Nilai klaim ({$claimedAmount}) melebihi uang pertanggungan polis ({$policy->sum_insured_idr}).");
        }

        // Prevent duplicate claim on same policy with same description
        $existing = InsuranceClaimPenuh::where('policy_id', $policy->id)
            ->where('incident_description', $payload['incident_description'])
            ->where('status', '!=', 'rejected')
            ->exists();

        if ($existing) {
            throw new DomainException("Duplikasi klaim terdeteksi untuk insiden pada polis {$policy->policy_number}.");
        }

        $claimCount = InsuranceClaimPenuh::count() + 1;
        $claimNumber = sprintf('CLM-%s-%06d', $policy->product->line_category, $claimCount);

        return InsuranceClaimPenuh::create([
            'claim_number' => $claimNumber,
            'policy_id' => $policy->id,
            'incident_date' => $payload['incident_date'],
            'incident_description' => $payload['incident_description'],
            'claimed_amount_idr' => $claimedAmount,
            'approved_amount_idr' => 0,
            'subrogation_recovered_idr' => 0,
            'reinsurance_recovered_idr' => 0,
            'net_payout_idr' => 0,
            'status' => 'registered',
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Approve and settle claim with subrogation & reinsurance recovery calculation.
     *
     * @param  array{approved_amount_idr: int, subrogation_recovered_idr?: int, approver_user_ref: int}  $payload
     */
    public function settleClaim(int $claimId, array $payload, string $idempotencyKey): InsuranceClaimPenuh
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $claim = InsuranceClaimPenuh::with('policy.product')->findOrFail($claimId);
        if ($claim->status === 'paid') {
            return $claim;
        }

        $approvedAmount = (int) $payload['approved_amount_idr'];
        if ($approvedAmount <= 0 || $approvedAmount > $claim->claimed_amount_idr) {
            throw new InvalidArgumentException('Nilai persetujuan klaim tidak valid.');
        }

        $approverUserRef = (int) $payload['approver_user_ref'];

        // Four-eyes validation for large claims > 50jt
        if ($approvedAmount > self::FOUR_EYES_THRESHOLD_IDR && $approverUserRef <= 0) {
            throw new DomainException('Klaim di atas Rp50.000.000 membutuhkan otorisasi Four-Eyes manajer klaim.');
        }

        $subrogation = (int) ($payload['subrogation_recovered_idr'] ?? 0);
        $subrogation = min($subrogation, $approvedAmount);

        // Treaty reinsurance recovery: ceded quota share % of (approved - subrogation)
        $quotaSharePct = (int) ($claim->policy->product->quota_share_pct ?? 40);
        $netBeforeReins = $approvedAmount - $subrogation;
        $reinsuranceRecovery = (int) intdiv($netBeforeReins * $quotaSharePct, 100);
        $netPayout = $netBeforeReins - $reinsuranceRecovery;

        return DB::transaction(function () use (
            $claim,
            $approvedAmount,
            $subrogation,
            $reinsuranceRecovery,
            $netPayout,
            $approverUserRef,
            $idempotencyKey
        ) {
            $claim->update([
                'approved_amount_idr' => $approvedAmount,
                'subrogation_recovered_idr' => $subrogation,
                'reinsurance_recovered_idr' => $reinsuranceRecovery,
                'net_payout_idr' => $netPayout,
                'status' => 'paid',
                'approved_by_user_ref' => $approverUserRef,
                'approved_at' => now(),
            ]);

            // Update status polis jika klaim total sum insured
            if ($approvedAmount >= $claim->policy->sum_insured_idr) {
                $claim->policy->update(['status' => 'claimed']);
            }

            // Double-entry ledger settlement:
            // Debit ins:claim_expense:IDR (netPayout)
            // Debit reins:claims_recoverable:IDR (reinsuranceRecovery)
            // Kredit ins:claim_clearing:IDR (approvedAmount - subrogation)
            $this->ledgerService->post(new PostingDTO(
                type: InsuranceTransactionTypes::INSURANCE_CLAIM_PAYOUT->value,
                description: "Penyelesaian Klaim {$claim->claim_number}",
                idempotencyKey: "ins:claim:settle:{$claim->claim_number}:{$idempotencyKey}",
                entries: [
                    PostingEntryDTO::forCode('ins:claim_expense:IDR', 'IDR', $netPayout),
                    PostingEntryDTO::forCode('reins:claims_recoverable:IDR', 'IDR', $reinsuranceRecovery),
                    PostingEntryDTO::forCode('ins:claim_clearing:IDR', 'IDR', -($approvedAmount - $subrogation)),
                ],
                referenceType: 'ins_claim_penuh',
                referenceId: (string) $claim->id,
            ));

            return $claim;
        });
    }

    /**
     * PSAK 62 Unearned Premium Reserve (UPR) pro-rata temporis (in integer IDR).
     */
    public function calculateUnearnedPremium(InsurancePolicyPenuh $policy, ?string $asOfDate = null): int
    {
        $asOf = $asOfDate ? strtotime($asOfDate) : time();
        $start = strtotime((string) $policy->starts_at);
        $end = strtotime((string) $policy->expires_at);

        if ($asOf <= $start) {
            return $policy->retained_premium_idr;
        }

        if ($asOf >= $end) {
            return 0;
        }

        $totalSeconds = max(1, $end - $start);
        $remainingSeconds = max(0, $end - $asOf);

        return (int) intdiv($policy->retained_premium_idr * $remainingSeconds, $totalSeconds);
    }

    /**
     * Estimates IBNR reserve for a line category using ChainLadder algorithm.
     *
     * @return array{total_ibnr_reserve: int, ultimate_losses: array<int, int>, ibnr_reserves: array<int, int>}
     */
    public function estimateCategoryIbnr(string $lineCategory): array
    {
        $records = InsuranceActuarialTriangle::where('line_category', strtoupper($lineCategory))
            ->orderBy('accident_year')
            ->orderBy('development_year')
            ->get();

        if ($records->isEmpty()) {
            return ['total_ibnr_reserve' => 0, 'ultimate_losses' => [], 'ibnr_reserves' => []];
        }

        $triangle = [];
        $minYear = $records->min('accident_year');

        foreach ($records as $r) {
            $i = (int) ($r->accident_year - $minYear);
            $j = (int) $r->development_year;
            $triangle[$i][$j] = (int) $r->cumulative_paid_idr;
        }

        ksort($triangle);
        foreach ($triangle as &$row) {
            ksort($row);
        }

        if (count($triangle) < 2) {
            return ['total_ibnr_reserve' => 0, 'ultimate_losses' => [], 'ibnr_reserves' => []];
        }

        $cl = ChainLadder::estimateIbnr($triangle);

        return [
            'total_ibnr_reserve' => $cl['total_ibnr_reserve'],
            'ultimate_losses' => $cl['ultimate_losses'],
            'ibnr_reserves' => $cl['ibnr_reserves'],
        ];
    }
}
