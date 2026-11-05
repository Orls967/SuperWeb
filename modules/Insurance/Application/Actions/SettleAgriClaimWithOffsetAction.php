<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Agri\Contracts\FarmerLoanOffset;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\AgriInsuranceClaim;
use Modules\Insurance\Domain\Models\AgriInsurancePolicy;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

final class SettleAgriClaimWithOffsetAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly FarmerLoanOffset $loanOffset,
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Memproses klaim parametrik pertanian dan memotong saldo cicilan pinjaman petani (Fase 160.2).
     */
    public function execute(
        string $policyNumber,
        string $claimNumber,
        int $actualIndexVal,
        string $actorId = '1',
        string $idempotencyKey = '',
    ): AgriInsuranceClaim {
        $policy = AgriInsurancePolicy::where('policy_number', $policyNumber)->firstOrFail();
        $key = $idempotencyKey !== '' ? $idempotencyKey : ('agri:claim:'.$claimNumber);

        return $this->guard->run(
            key: $key,
            scope: 'ins:agri:claim:'.$claimNumber,
            payload: [
                'policy_number' => $policyNumber,
                'claim_number' => $claimNumber,
                'actual_index' => $actualIndexVal,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use ($policy, $claimNumber, $actualIndexVal) {
                return DB::transaction(function () use ($policy, $claimNumber, $actualIndexVal) {
                    $isTriggered = match ($policy->index_type) {
                        'DROUGHT', 'NDVI_INDEX' => $actualIndexVal <= $policy->trigger_threshold_val,
                        default => $actualIndexVal >= $policy->trigger_threshold_val, // RAINFALL flood
                    };

                    if (! $isTriggered) {
                        throw new InvalidArgumentException("Indeks aktual ({$actualIndexVal}) belum melewati ambang batas pemicu polis ({$policy->trigger_threshold_val}).");
                    }

                    $grossClaim = (int) $policy->sum_insured_idr;

                    // 1. Ambil pinjaman outstanding petani dari modul Agri
                    $outstandingLoan = $this->loanOffset->getOutstandingLoanBalance($policy->farmer_party_id);
                    $loanOffsetAmount = min($grossClaim, $outstandingLoan);
                    $netFarmerPayout = $grossClaim - $loanOffsetAmount;

                    // 2. Eksekusi offset pinjaman via kontrak G7-K2
                    if ($loanOffsetAmount > 0) {
                        $this->loanOffset->offsetLoanBalance(
                            farmerPartyId: $policy->farmer_party_id,
                            offsetAmountIdr: $loanOffsetAmount,
                            referenceClaimNumber: $claimNumber,
                        );
                    }

                    $claim = AgriInsuranceClaim::create([
                        'claim_number' => $claimNumber,
                        'policy_id' => $policy->id,
                        'actual_index_val' => $actualIndexVal,
                        'gross_claim_idr' => $grossClaim,
                        'loan_offset_amount_idr' => $loanOffsetAmount,
                        'net_farmer_payout_idr' => $netFarmerPayout,
                        'status' => 'settled',
                        'adjudicated_at' => CarbonImmutable::now(),
                    ]);

                    // Posting pembayaran klaim ke ledger
                    $lines = Posting::lines();
                    $lines->debit('ins:claim_expense:IDR', $grossClaim);
                    $lines->credit('ins:claim_clearing:IDR', $grossClaim);

                    $this->ledger->post(new PostingDTO(
                        type: InsuranceTransactionTypes::INSURANCE_CLAIM_PAYOUT->value,
                        description: "Agri Parametric Claim Payout {$claimNumber} (Offset: {$loanOffsetAmount}, Net: {$netFarmerPayout})",
                        idempotencyKey: 'agri:payout:'.$claimNumber,
                        entries: $lines->toEntries('IDR'),
                        referenceType: 'ins_agri_claim',
                        referenceId: $claimNumber,
                    ));

                    return $claim;
                });
            }
        );
    }
}
