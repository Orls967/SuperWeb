<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Contracts\PatientReferralBooker;
use Modules\Hospital\Domain\Models\EPharmacyOrder;

class IssueElectronicPrescriptionAction
{
    public function __construct(
        protected PatientReferralBooker $booker
    ) {}

    /**
     * @param array{
     *     tele_consult_id: int,
     *     patient_id: int,
     *     pharmacy_branch_id: int,
     *     drug_code: string,
     *     drug_name: string,
     *     quantity: int,
     *     total_price_idr: int,
     *     drug_classification?: string,
     *     doctor_sip_number: string,
     *     is_chronic_subscription?: bool
     * } $data
     */
    public function execute(array $data): EPharmacyOrder
    {
        return $this->booker->issueElectronicPrescription($data);
    }
}
