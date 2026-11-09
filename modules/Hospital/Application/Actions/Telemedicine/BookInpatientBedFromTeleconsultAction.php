<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Contracts\PatientReferralBooker;

class BookInpatientBedFromTeleconsultAction
{
    public function __construct(
        protected PatientReferralBooker $booker
    ) {}

    /**
     * @return array{
     *     booking_reference: string,
     *     patient_id: int,
     *     bed_id: int,
     *     bed_code: string,
     *     ward_name: string,
     *     encounter_id: int,
     *     required_escrow_deposit_idr: int,
     *     status: string
     * }
     */
    public function execute(
        int $teleConsultId,
        string $targetWardClass,
        string $primaryDiagnosisIcd10
    ): array {
        return $this->booker->bookInpatientFromReferral(
            teleConsultId: $teleConsultId,
            targetWardClass: $targetWardClass,
            primaryDiagnosisIcd10: $primaryDiagnosisIcd10
        );
    }
}
