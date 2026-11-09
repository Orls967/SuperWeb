<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Contracts\PatientReferralBooker;
use Modules\Hospital\Domain\Models\TeleConsult;

class BookTeleconsultationAction
{
    public function __construct(
        protected PatientReferralBooker $booker
    ) {}

    /**
     * @param array{
     *     patient_id: int,
     *     doctor_id: int,
     *     channel_type?: string,
     *     chief_complaint: string,
     *     clinical_notes?: string|null
     * } $data
     */
    public function execute(array $data): TeleConsult
    {
        return $this->booker->bookTeleconsultation($data);
    }
}
