<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Contracts\PatientReferralBooker;
use Modules\Hospital\Domain\Models\BpjsReferralBridge;

class BridgeSisruteReferralAction
{
    public function __construct(
        protected PatientReferralBooker $booker
    ) {}

    /**
     * @param array{
     *     patient_id: int,
     *     tele_consult_id?: int|null,
     *     encounter_id?: int|null,
     *     referral_direction?: string,
     *     referral_type?: string,
     *     origin_faskes_code: string,
     *     origin_faskes_name: string,
     *     destination_faskes_code: string,
     *     destination_faskes_name: string,
     *     diagnosis_icd10: string,
     *     clinical_summary: string,
     *     transportation_type?: string,
     *     target_polyclinic_or_ward: string
     * } $data
     */
    public function execute(array $data): BpjsReferralBridge
    {
        return $this->booker->bridgeBpjsReferral($data);
    }
}
