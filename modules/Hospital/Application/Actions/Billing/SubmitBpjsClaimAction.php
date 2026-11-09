<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Billing;

use DomainException;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Domain\Services\InaCbgGroupingService;
use Modules\Hospital\Domain\StateMachines\BpjsClaimStateMachine;

final class SubmitBpjsClaimAction
{
    public function __construct(
        private readonly InaCbgGroupingService $groupingService
    ) {}

    /**
     * @param  array{
     *     billing_episode_id: int,
     *     primary_icd10: string,
     *     secondary_icd10?: array,
     *     procedures_icd9?: array,
     *     entitled_class: string,
     *     actual_class: string
     * }  $data
     */
    public function execute(array $data): BpjsClaim
    {
        $episodeId = (int) $data['billing_episode_id'];
        $episode = BillingEpisode::find($episodeId);

        if (! $episode) {
            throw new DomainException("Billing episode #{$episodeId} tidak ditemukan.");
        }

        $calculation = $this->groupingService->groupAndCalculateTariff(
            primaryDiagnosisCode: $data['primary_icd10'],
            secondaryDiagnosisCodes: $data['secondary_icd10'] ?? [],
            procedureCodes: $data['procedures_icd9'] ?? [],
            entitledClass: $data['entitled_class'],
            actualClass: $data['actual_class']
        );

        $claim = BpjsClaim::create([
            'claim_number' => 'CLM-'.date('Ymd').'-'.str_pad((string) $episode->id, 6, '0', STR_PAD_LEFT),
            'encounter_id' => $episode->encounter_id,
            'ina_cbg_code' => $calculation->cbgCode,
            'ina_cbg_tariff_idr' => $calculation->entitledClassTariffIdr,
            'hospital_tariff_idr' => $episode->total_charges_idr,
            'variance_amount_idr' => $episode->total_charges_idr - $calculation->entitledClassTariffIdr,
            'patient_copay_amount_idr' => $calculation->classUpgradeExcessIdr,
            'claim_status' => BpjsClaimStateMachine::STATUS_SUBMITTED,
        ]);

        return $claim;
    }
}
