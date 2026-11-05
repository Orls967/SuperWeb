<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\AgriInsurancePolicy;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

final class IssueAgriParametricPolicyAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Menerbitkan polis asuransi pertanian berbasis indeks cuaca/NDVI (Fase 160.2).
     */
    public function execute(
        string $policyNumber,
        string $farmerPartyId,
        string $commodity,
        int $farmAreaHa,
        int $sumInsuredIdr,
        int $premiumAnnualIdr,
        string $indexType,
        int $triggerThresholdVal,
        string $actorId = '1',
        string $idempotencyKey = '',
    ): AgriInsurancePolicy {
        $key = $idempotencyKey !== '' ? $idempotencyKey : ('agri:iss:'.$policyNumber);

        return $this->guard->run(
            key: $key,
            scope: 'ins:agri:issue:'.$policyNumber,
            payload: [
                'policy_number' => $policyNumber,
                'farmer_id' => $farmerPartyId,
                'commodity' => $commodity,
                'premium' => $premiumAnnualIdr,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use (
                $policyNumber,
                $farmerPartyId,
                $commodity,
                $farmAreaHa,
                $sumInsuredIdr,
                $premiumAnnualIdr,
                $indexType,
                $triggerThresholdVal
            ) {
                return DB::transaction(function () use (
                    $policyNumber,
                    $farmerPartyId,
                    $commodity,
                    $farmAreaHa,
                    $sumInsuredIdr,
                    $premiumAnnualIdr,
                    $indexType,
                    $triggerThresholdVal
                ) {
                    $now = CarbonImmutable::now();

                    $policy = AgriInsurancePolicy::create([
                        'policy_number' => $policyNumber,
                        'farmer_party_id' => $farmerPartyId,
                        'commodity' => $commodity,
                        'farm_area_ha' => $farmAreaHa,
                        'sum_insured_idr' => $sumInsuredIdr,
                        'premium_annual_idr' => $premiumAnnualIdr,
                        'index_type' => $indexType,
                        'trigger_threshold_val' => $triggerThresholdVal,
                        'status' => 'active',
                        'effective_date' => $now->toDateString(),
                        'expiry_date' => $now->addYear()->toDateString(),
                    ]);

                    // Posting penerimaan premi asuransi tani ke ledger
                    $lines = Posting::lines();
                    $lines->debit('ins:reserve_pool:IDR', $premiumAnnualIdr);
                    $lines->credit('ins:unearned_premium:IDR', $premiumAnnualIdr);

                    $this->ledger->post(new PostingDTO(
                        type: InsuranceTransactionTypes::INSURANCE_PREMIUM->value,
                        description: "Agri Parametric Insurance Premium Policy {$policyNumber}",
                        idempotencyKey: 'agri:prem:'.$policyNumber,
                        entries: $lines->toEntries('IDR'),
                        referenceType: 'ins_agri_policy',
                        referenceId: $policyNumber,
                    ));

                    return $policy;
                });
            }
        );
    }
}
