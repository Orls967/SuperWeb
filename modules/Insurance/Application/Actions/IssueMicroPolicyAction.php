<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\MicroInsurancePolicy;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

final class IssueMicroPolicyAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Menerbitkan polis asuransi mikro massal instan (Fase 160.3).
     */
    public function execute(
        string $policyNumber,
        string $memberPartyId,
        string $productType,
        int $dailyPremiumIdr,
        int $sumInsuredIdr,
        int $durationDays = 1,
        string $actorId = '1',
        string $idempotencyKey = '',
    ): MicroInsurancePolicy {
        $key = $idempotencyKey !== '' ? $idempotencyKey : ('micro:iss:'.$policyNumber);

        return $this->guard->run(
            key: $key,
            scope: 'ins:micro:issue:'.$policyNumber,
            payload: [
                'policy_number' => $policyNumber,
                'member_id' => $memberPartyId,
                'product_type' => $productType,
                'daily_premium' => $dailyPremiumIdr,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use (
                $policyNumber,
                $memberPartyId,
                $productType,
                $dailyPremiumIdr,
                $sumInsuredIdr,
                $durationDays
            ) {
                return DB::transaction(function () use (
                    $policyNumber,
                    $memberPartyId,
                    $productType,
                    $dailyPremiumIdr,
                    $sumInsuredIdr,
                    $durationDays
                ) {
                    $now = CarbonImmutable::now();
                    $totalPremium = $dailyPremiumIdr * $durationDays;

                    $policy = MicroInsurancePolicy::create([
                        'policy_number' => $policyNumber,
                        'member_party_id' => $memberPartyId,
                        'product_type' => $productType,
                        'daily_premium_idr' => $dailyPremiumIdr,
                        'sum_insured_idr' => $sumInsuredIdr,
                        'duration_days' => $durationDays,
                        'status' => 'active',
                        'issued_at' => $now,
                        'expires_at' => $now->addDays($durationDays),
                    ]);

                    // Posting premi mikro ke ledger
                    $lines = Posting::lines();
                    $lines->debit('ins:reserve_pool:IDR', $totalPremium);
                    $lines->credit('ins:unearned_premium:IDR', $totalPremium);

                    $this->ledger->post(new PostingDTO(
                        type: InsuranceTransactionTypes::INSURANCE_PREMIUM->value,
                        description: "Micro-insurance Premium Policy {$policyNumber}",
                        idempotencyKey: 'micro:prem:'.$policyNumber,
                        entries: $lines->toEntries('IDR'),
                        referenceType: 'ins_micro_policy',
                        referenceId: $policyNumber,
                    ));

                    return $policy;
                });
            }
        );
    }
}
