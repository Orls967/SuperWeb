<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\LifePolicy;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

final class ClaimLifeBenefitAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Memproses klaim santunan wafat (Death Benefit) asuransi jiwa dengan prinsip empat-mata (four-eyes).
     *
     * @return array{
     *     policy_number: string,
     *     total_payout_idr: int,
     *     beneficiary_payouts: list<array{beneficiary_party_id: string, relationship: string, amount_idr: int}>,
     *     ledger_transaction_id: int
     * }
     */
    public function execute(
        string $policyNumber,
        string $deathCertificateRef,
        string $adjusterId,
        string $approverId,
        string $idempotencyKey,
    ): array {
        if ($adjusterId === $approverId) {
            throw new InvalidArgumentException('Klaim asuransi jiwa membutuhkan persetujuan empat-mata: adjuster dan approver tidak boleh orang yang sama.');
        }

        $policy = LifePolicy::with('beneficiaries')->where('policy_number', $policyNumber)->firstOrFail();
        if ($policy->status !== 'active') {
            throw new InvalidArgumentException("Polis {$policyNumber} tidak dalam status aktif (status: {$policy->status}).");
        }

        return $this->guard->run(
            key: $idempotencyKey,
            scope: 'ins:life:claim:'.$policyNumber,
            payload: [
                'policy_number' => $policyNumber,
                'death_ref' => $deathCertificateRef,
                'adjuster_id' => $adjusterId,
                'approver_id' => $approverId,
            ],
            actorId: is_numeric($approverId) ? (int) $approverId : null,
            operation: function () use ($policy, $policyNumber) {
                return DB::transaction(function () use ($policy, $policyNumber) {
                    $sumInsured = $policy->sum_insured_idr;
                    $savingAccumulated = $policy->saving_accumulated_idr;
                    $totalPayout = $sumInsured + $savingAccumulated;

                    // Posting pembayaran klaim ke ledger
                    $lines = Posting::lines();
                    $lines->debit('ins:claim_expense:IDR', $totalPayout);
                    $lines->credit('ins:claim_clearing:IDR', $totalPayout);

                    $tx = $this->ledger->post(new PostingDTO(
                        type: InsuranceTransactionTypes::INSURANCE_CLAIM_PAYOUT->value,
                        description: "Death Benefit Claim Payout for Policy {$policyNumber}",
                        idempotencyKey: 'clm:death:'.$policyNumber,
                        entries: $lines->toEntries('IDR'),
                        referenceType: 'ins_lh_life_policy',
                        referenceId: $policyNumber,
                    ));

                    // Alokasi santunan per penerima manfaat
                    $beneficiaryPayouts = [];
                    foreach ($policy->beneficiaries as $ben) {
                        $shareAmount = (int) intdiv($totalPayout * $ben->share_percentage_bps, 10000);
                        $beneficiaryPayouts[] = [
                            'beneficiary_party_id' => $ben->beneficiary_party_id,
                            'relationship' => $ben->relationship,
                            'amount_idr' => $shareAmount,
                        ];
                    }

                    $policy->update(['status' => 'claimed']);

                    return [
                        'policy_number' => $policyNumber,
                        'total_payout_idr' => $totalPayout,
                        'beneficiary_payouts' => $beneficiaryPayouts,
                        'ledger_transaction_id' => $tx->id,
                    ];
                });
            }
        );
    }
}
