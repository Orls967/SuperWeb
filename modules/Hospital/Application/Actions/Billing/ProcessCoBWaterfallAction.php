<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Billing;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Application\DTOs\WaterfallBreakdownDTO;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Services\CoordinationOfBenefitsWaterfallService;
use Modules\Hospital\Domain\Services\HospitalAccountsPostingService;
use Modules\Hospital\Domain\StateMachines\BillingEpisodeStateMachine;

final class ProcessCoBWaterfallAction
{
    public function __construct(
        private readonly CoordinationOfBenefitsWaterfallService $waterfallService,
        private readonly HospitalAccountsPostingService $accountsPostingService
    ) {}

    /**
     * @param  array{billing_episode_id: int, bpjs_tariff_idr: int, private_insurance_policy_no?: ?string, guarantee_letter_no?: ?string}  $data
     */
    public function execute(array $data): WaterfallBreakdownDTO
    {
        $episodeId = (int) $data['billing_episode_id'];
        $episode = BillingEpisode::find($episodeId);

        if (! $episode) {
            throw new DomainException("Billing episode #{$episodeId} tidak ditemukan.");
        }

        return DB::transaction(function () use ($episode, $data) {
            $bpjsTariff = max(0, (int) $data['bpjs_tariff_idr']);
            $privatePolicy = $data['private_insurance_policy_no'] ?? null;
            $glNo = $data['guarantee_letter_no'] ?? null;

            $breakdown = $this->waterfallService->executeWaterfall(
                episode: $episode,
                bpjsEligibleTariffIdr: $bpjsTariff,
                privateInsurancePolicyNo: $privatePolicy,
                guaranteeLetterNo: $glNo
            );

            // Transisi status episode ke SETTLED jika seimbang
            if ($breakdown->settlementStatus === 'BALANCED') {
                BillingEpisodeStateMachine::transition($episode, BillingEpisodeStateMachine::STATUS_SETTLED);
            } else {
                BillingEpisodeStateMachine::transition($episode, BillingEpisodeStateMachine::STATUS_WATERFALL_PENDING);
            }

            // Bukukan ke COA HospitalAccounts
            $this->accountsPostingService->postWaterfallSettlement($episode, $breakdown);

            return $breakdown;
        });
    }
}
