<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\LifeBeneficiary;
use Modules\Insurance\Domain\Models\LifePolicy;
use Modules\Insurance\Domain\Models\UnitLinkFund;
use Modules\Insurance\Domain\Models\UnitLinkHolding;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;
use Modules\Shared\Domain\Algorithms\LifeContingency\TermLifePremium;

final class IssueLifePolicyAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly TermLifePremium $lifeContingency,
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * @param  list<array{beneficiary_party_id: string, relationship: string, share_percentage_bps: int, is_primary?: bool}>  $beneficiaries
     * @param  list<array{fund_code: string, allocation_pct: int}>  $unitLinkAllocations
     */
    public function execute(
        string $policyNumber,
        string $insuredPartyId,
        string $planType,
        int $entryAge,
        int $termYears,
        int $sumInsuredIdr,
        array $beneficiaries,
        string $actorId,
        string $idempotencyKey,
        array $unitLinkAllocations = [],
    ): LifePolicy {
        $totalShare = array_sum(array_column($beneficiaries, 'share_percentage_bps'));
        if ($totalShare !== 10000) {
            throw new InvalidArgumentException("Total alokasi penerima manfaat harus persis 10.000 bps (100%), saat ini {$totalShare} bps.");
        }

        // Kalkulasi premi aktuaria bersertifikat (A7)
        $actuarialCalc = $this->lifeContingency->calculate(
            age: $entryAge,
            termYears: $termYears,
            sumInsuredIdr: $sumInsuredIdr,
            interestRateBps: 500
        );
        $annualPremium = $actuarialCalc->annualNetPremiumIdr;

        return $this->guard->run(
            key: $idempotencyKey,
            scope: 'ins:life:issue:'.$policyNumber,
            payload: [
                'policy_number' => $policyNumber,
                'insured_party_id' => $insuredPartyId,
                'sum_insured' => $sumInsuredIdr,
                'premium' => $annualPremium,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use ($policyNumber, $insuredPartyId, $planType, $entryAge, $termYears, $sumInsuredIdr, $annualPremium, $beneficiaries, $unitLinkAllocations) {
                return DB::transaction(function () use ($policyNumber, $insuredPartyId, $planType, $entryAge, $termYears, $sumInsuredIdr, $annualPremium, $beneficiaries, $unitLinkAllocations) {
                    $now = CarbonImmutable::now();
                    $maturityDate = $now->addYears($termYears)->toDateString();

                    // Posting penerimaan premi awal ke ledger
                    $lines = Posting::lines();
                    $lines->debit('ins:reserve_pool:IDR', $annualPremium);
                    $lines->credit('ins:unearned_premium:IDR', $annualPremium);

                    $tx = $this->ledger->post(new PostingDTO(
                        type: InsuranceTransactionTypes::INSURANCE_PREMIUM->value,
                        description: "Initial Annual Premium Life Policy {$policyNumber}",
                        idempotencyKey: 'life:prem:'.$policyNumber,
                        entries: $lines->toEntries('IDR'),
                        referenceType: 'ins_lh_life_policy',
                        referenceId: $policyNumber,
                    ));

                    $policy = LifePolicy::create([
                        'policy_number' => $policyNumber,
                        'insured_party_id' => $insuredPartyId,
                        'plan_type' => $planType,
                        'entry_age' => $entryAge,
                        'term_years' => $termYears,
                        'sum_insured_idr' => $sumInsuredIdr,
                        'annual_premium_idr' => $annualPremium,
                        'saving_accumulated_idr' => ($planType === 'ENDOWMENT_SAVING') ? (int) intdiv($annualPremium * 30, 100) : 0,
                        'status' => 'active',
                        'issued_date' => $now->toDateString(),
                        'maturity_date' => $maturityDate,
                        'ledger_transaction_id' => $tx->id,
                    ]);

                    foreach ($beneficiaries as $ben) {
                        LifeBeneficiary::create([
                            'life_policy_id' => $policy->id,
                            'beneficiary_party_id' => $ben['beneficiary_party_id'],
                            'relationship' => $ben['relationship'],
                            'share_percentage_bps' => $ben['share_percentage_bps'],
                            'is_primary' => $ben['is_primary'] ?? true,
                        ]);
                    }

                    // Alokasi unit link jika ada
                    if ($unitLinkAllocations !== []) {
                        $investablePremium = (int) intdiv($annualPremium * 70, 100);
                        foreach ($unitLinkAllocations as $alloc) {
                            $fund = UnitLinkFund::where('fund_code', $alloc['fund_code'])->first();
                            if ($fund !== null && $fund->current_nav_per_unit_idr > 0) {
                                $portionIdr = (int) intdiv($investablePremium * $alloc['allocation_pct'], 100);
                                $unitsPurchased = (int) intdiv($portionIdr * 1000, $fund->current_nav_per_unit_idr);

                                UnitLinkHolding::create([
                                    'life_policy_id' => $policy->id,
                                    'fund_id' => $fund->id,
                                    'units_held' => $unitsPurchased,
                                    'acquisition_cost_idr' => $portionIdr,
                                    'current_valuation_idr' => $portionIdr,
                                ]);

                                $fund->increment('total_units', $unitsPurchased);
                                $fund->increment('total_aum_idr', $portionIdr);
                            }
                        }
                    }

                    return $policy;
                });
            }
        );
    }
}
