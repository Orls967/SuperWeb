<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Contracts\PatientReferralBooker;
use Modules\Hospital\Domain\Models\PharmacyBranch;

class RoutePrescriptionToNearestBranchAction
{
    public function __construct(
        protected PatientReferralBooker $booker
    ) {}

    public function execute(
        string $drugCode,
        int $quantity,
        float $patientLatitude,
        float $patientLongitude,
        float $maxRadiusKm = 25.0
    ): PharmacyBranch {
        return $this->booker->routePrescriptionToPharmacy(
            drugCode: $drugCode,
            quantity: $quantity,
            patientLatitude: $patientLatitude,
            patientLongitude: $patientLongitude,
            maxRadiusKm: $maxRadiusKm
        );
    }
}
