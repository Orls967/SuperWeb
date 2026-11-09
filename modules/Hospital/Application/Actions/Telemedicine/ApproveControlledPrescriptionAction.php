<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Services\ControlledSubstanceDualApprovalService;

class ApproveControlledPrescriptionAction
{
    public function __construct(
        protected ControlledSubstanceDualApprovalService $dualApprovalService
    ) {}

    public function execute(
        EPharmacyOrder $order,
        int $approvingDoctorId,
        string $approvingDoctorSip,
        string $clinicalReviewNotes
    ): EPharmacyOrder {
        return $this->dualApprovalService->approveControlledPrescription(
            order: $order,
            approvingDoctorId: $approvingDoctorId,
            approvingDoctorSip: $approvingDoctorSip,
            clinicalReviewNotes: $clinicalReviewNotes
        );
    }
}
