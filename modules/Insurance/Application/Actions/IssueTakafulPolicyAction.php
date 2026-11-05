<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\TakafulFund;
use Modules\Insurance\Domain\Models\TakafulParticipant;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

final class IssueTakafulPolicyAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Menerbitkan polis partisipasi syariah / Takaful (Fase 160.1).
     */
    public function execute(
        string $policyNumber,
        string $participantPartyId,
        string $fundCode,
        string $planType,
        int $annualContributionIdr,
        int $sumCoveredIdr,
        int $tabarruPortionBps = 7000,
        int $ujrahPortionBps = 3000,
        string $actorId = '1',
        string $idempotencyKey = '',
    ): TakafulParticipant {
        $fund = TakafulFund::where('fund_code', $fundCode)->firstOrFail();
        $key = $idempotencyKey !== '' ? $idempotencyKey : ('tk:iss:'.$policyNumber);

        return $this->guard->run(
            key: $key,
            scope: 'ins:takaful:issue:'.$policyNumber,
            payload: [
                'policy_number' => $policyNumber,
                'party_id' => $participantPartyId,
                'fund_code' => $fundCode,
                'contribution' => $annualContributionIdr,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use (
                $fund,
                $policyNumber,
                $participantPartyId,
                $planType,
                $annualContributionIdr,
                $sumCoveredIdr,
                $tabarruPortionBps,
                $ujrahPortionBps
            ) {
                return DB::transaction(function () use (
                    $fund,
                    $policyNumber,
                    $participantPartyId,
                    $planType,
                    $annualContributionIdr,
                    $sumCoveredIdr,
                    $tabarruPortionBps,
                    $ujrahPortionBps
                ) {
                    $now = CarbonImmutable::now();
                    $tabarruAmount = (int) intdiv($annualContributionIdr * $tabarruPortionBps, 10000);
                    $ujrahAmount = $annualContributionIdr - $tabarruAmount;

                    $participant = TakafulParticipant::create([
                        'participant_party_id' => $participantPartyId,
                        'policy_number' => $policyNumber,
                        'fund_id' => $fund->id,
                        'plan_type' => $planType,
                        'contribution_annual_idr' => $annualContributionIdr,
                        'tabarru_portion_bps' => $tabarruPortionBps,
                        'ujrah_portion_bps' => $ujrahPortionBps,
                        'sum_covered_idr' => $sumCoveredIdr,
                        'status' => 'active',
                        'effective_date' => $now->toDateString(),
                        'expiry_date' => $now->addYear()->toDateString(),
                    ]);

                    // Update saldo dana tabarru'
                    $fund->increment('balance_idr', $tabarruAmount);
                    $fund->increment('total_contributions_idr', $tabarruAmount);

                    // Double-entry posting ke ledger: Kas Tabarru vs Kewajiban Premi
                    $lines = Posting::lines();
                    $lines->debit('ins:reserve_pool:IDR', $annualContributionIdr);
                    $lines->credit('ins:unearned_premium:IDR', $annualContributionIdr);

                    $this->ledger->post(new PostingDTO(
                        type: InsuranceTransactionTypes::INSURANCE_PREMIUM->value,
                        description: "Takaful Contribution for Policy {$policyNumber} (Tabarru: {$tabarruAmount}, Ujrah: {$ujrahAmount})",
                        idempotencyKey: 'tk:prem:'.$policyNumber,
                        entries: $lines->toEntries('IDR'),
                        referenceType: 'ins_tk_participant',
                        referenceId: $policyNumber,
                    ));

                    return $participant;
                });
            }
        );
    }
}
