<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Listeners;

use Modules\Hospital\Application\Events\AdmissionCreatedEvent;
use Modules\Hospital\Application\Events\AdmissionDischargedEvent;
use Modules\Hospital\Application\Events\BedAssignedEvent;
use Modules\Hospital\Domain\Services\PatientPassportService;

final class RecordPassportAuditLogListener
{
    private PatientPassportService $passportService;

    public function __construct(PatientPassportService $passportService)
    {
        $this->passportService = $passportService;
    }

    public function handleAdmission(AdmissionCreatedEvent $event): void
    {
        $this->passportService->appendEvent(
            patientId: $event->admission->patient_id,
            eventType: 'ADMISSION_REGISTERED',
            eventPayload: ['admission_id' => $event->admission->id],
            actorId: $event->actorId
        );
    }

    public function handleDischarge(AdmissionDischargedEvent $event): void
    {
        $this->passportService->appendEvent(
            patientId: $event->admission->patient_id,
            eventType: 'DISCHARGE_COMPLETED',
            eventPayload: [
                'admission_id' => $event->admission->id,
                'disposition' => $event->dischargeDisposition,
            ],
            actorId: $event->actorId
        );
    }

    public function handleBedAssignment(BedAssignedEvent $event): void
    {
        $this->passportService->appendEvent(
            patientId: $event->patientId,
            eventType: 'BED_ASSIGNED',
            eventPayload: [
                'bed_id' => $event->bed->id,
                'bed_code' => $event->bed->bed_code,
                'class' => $event->bed->class,
            ],
            actorId: $event->actorId
        );
    }
}
