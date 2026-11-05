<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Insurance\Application\Actions\CedeClaimAction;
use Modules\Insurance\Application\Actions\CedePolicyPremiumAction;
use Modules\Insurance\Application\Support\InsuranceNumbering;
use Modules\Insurance\Domain\Exceptions\IdempotencyKeyConflictException;
use Modules\Insurance\Domain\Models\InsuranceActuarialTriangle;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Insurance\Domain\Models\InsurancePremiumSchedule;
use Modules\Insurance\Domain\Models\InsuranceProductPenuh;
use Modules\Insurance\Ledger\InsuranceAccounts;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;
use Modules\Shared\Domain\Algorithms\Actuarial\ChainLadder;

final class FullInsuranceService
{
    public const FOUR_EYES_THRESHOLD_IDR = 50000000; // Rp50.000.000

    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly ClockInterface $clock,
        private readonly DocumentNumberingInterface $numbering,
        private readonly CedePolicyPremiumAction $cedePremium,
        private readonly CedeClaimAction $cedeClaim,
    ) {}

    /**
     * Rating engine: deterministically calculates gross, ceded, and retained premiums.
     *
     * @param  array{product_id: int, customer_party_ref: int, sum_insured_idr: int, risk_multiplier_bps?: int, starts_at: string, expires_at: string, installments?: int, region_code?: string|null}  $payload
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

        $existing = InsurancePolicyPenuh::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            if ($existing->product_id !== $product->id || $existing->sum_insured_idr !== $sumInsured || $existing->customer_party_ref !== (int) $payload['customer_party_ref']) {
                throw new IdempotencyKeyConflictException($idempotencyKey);
            }

            return $existing;
        }

        return DB::transaction(function () use ($payload, $product, $sumInsured, $grossPremium, $idempotencyKey) {
            $policyNumber = InsuranceNumbering::policy($this->numbering, $product->line_category);

            // Cession ke program treaty dilakukan CedePolicyPremiumAction; di sini seluruh premi bruto menjadi piutang & UPR.
            $policy = InsurancePolicyPenuh::create([
                'policy_number' => $policyNumber,
                'product_id' => $product->id,
                'customer_party_ref' => (int) $payload['customer_party_ref'],
                'region_code' => $payload['region_code'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'sum_insured_idr' => $sumInsured,
                'gross_premium_idr' => $grossPremium,
                'ceded_premium_idr' => 0,
                'retained_premium_idr' => $grossPremium,
                'starts_at' => $payload['starts_at'],
                'expires_at' => $payload['expires_at'],
                'status' => 'active',
            ]);

            $installments = max(1, (int) ($payload['installments'] ?? 1));
            $installmentAmount = (int) intdiv($grossPremium, $installments);
            $remainder = $grossPremium % $installments;

            for ($i = 1; $i <= $installments; $i++) {
                InsurancePremiumSchedule::create([
                    'policy_id' => $policy->id,
                    'installment_number' => $i,
                    'due_date' => $payload['starts_at'],
                    'amount_idr' => $installmentAmount + ($i === $installments ? $remainder : 0),
                    'payment_status' => 'paid',
                    'paid_at' => $this->clock->now(),
                ]);
            }

            $this->ledgerService->post(new PostingDTO(
                type: InsuranceTransactionTypes::INSURANCE_PREMIUM->value,
                description: "Penerbitan Polis Asuransi {$policyNumber}",
                idempotencyKey: "ins:bind:{$policy->id}",
                entries: Posting::lines()
                    ->debit(InsuranceAccounts::PREMIUM_RECEIVABLE, $grossPremium)
                    ->credit(InsuranceAccounts::UNEARNED_PREMIUM, $grossPremium)
                    ->toEntries('IDR'),
                referenceType: 'ins_policy_penuh',
                referenceId: (string) $policy->id,
            ));

            $this->cedePremium->execute($policy->id);

            return $policy->refresh();
        });
    }

    /**
     * Submit a claim against a policy with duplicate prevention.
     *
     * @param  array{policy_id: int, incident_date: string, incident_description: string, claimed_amount_idr: int, cat_event_code?: string|null}  $payload
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

        $claimNumber = InsuranceNumbering::claim($this->numbering, $policy->product->line_category);

        return InsuranceClaimPenuh::create([
            'claim_number' => $claimNumber,
            'policy_id' => $policy->id,
            'incident_date' => $payload['incident_date'],
            'incident_description' => $payload['incident_description'],
            'cat_event_code' => $payload['cat_event_code'] ?? null,
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
        $grossLoss = $approvedAmount - $subrogation;

        return DB::transaction(function () use ($claim, $approvedAmount, $subrogation, $grossLoss, $approverUserRef) {
            $claim->update([
                'approved_amount_idr' => $approvedAmount,
                'subrogation_recovered_idr' => $subrogation,
                'reinsurance_recovered_idr' => 0,
                'net_payout_idr' => $grossLoss,
                'status' => 'paid',
                'approved_by_user_ref' => $approverUserRef,
                'approved_at' => $this->clock->now(),
            ]);

            if ($approvedAmount >= $claim->policy->sum_insured_idr) {
                $claim->policy->update(['status' => 'claimed']);
            }

            // Beban klaim bruto (setelah subrogasi); recoverable reasuransi dibukukan CedeClaimAction.
            $this->ledgerService->post(new PostingDTO(
                type: InsuranceTransactionTypes::INSURANCE_CLAIM_PAYOUT->value,
                description: "Penyelesaian Klaim {$claim->claim_number}",
                idempotencyKey: "ins:claim:settle:{$claim->id}",
                entries: Posting::lines()
                    ->debit(InsuranceAccounts::CLAIM_EXPENSE, $grossLoss)
                    ->credit(InsuranceAccounts::CLAIM_CLEARING, $grossLoss)
                    ->toEntries('IDR'),
                referenceType: 'ins_claim_penuh',
                referenceId: (string) $claim->id,
            ));

            $this->cedeClaim->execute($claim->id);

            return $claim->refresh();
        });
    }

    /**
     * PSAK 62 Unearned Premium Reserve (UPR) pro-rata temporis (in integer IDR).
     */
    public function calculateUnearnedPremium(InsurancePolicyPenuh $policy, ?string $asOfDate = null): int
    {
        $asOf = $asOfDate ? strtotime($asOfDate) : $this->clock->now()->getTimestamp();
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
