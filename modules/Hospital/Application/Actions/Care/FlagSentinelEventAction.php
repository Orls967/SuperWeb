<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Carbon\CarbonImmutable;
use Modules\Hospital\Domain\Services\PatientPassportService;

final class FlagSentinelEventAction
{
    private PatientPassportService $passportService;

    public function __construct(PatientPassportService $passportService)
    {
        $this->passportService = $passportService;
    }

    /**
     * Menandai insiden keselamatan pasien (Sentinel Event / KTD / KNC)
     * dan mengunci audit trail untuk komite mutu keselamatan pasien (KMKP).
     *
     * @param array{
     *     admission_id: int,
     *     patient_id: int,
     *     incident_type: string, // SENTINEL, KTD, KNC, KTC, KPC
     *     chronology: string,
     *     severity_grading: string, // MERAH, KUNING, HIJAU, BIRU
     *     reported_by_user_id: int,
     *     immediate_action_taken: string
     * } $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $incidentId = 'INC-'.date('Ymd').'-'.strtoupper(substr(hash('sha256', uniqid()), 0, 6));

        $payload = [
            'incident_id' => $incidentId,
            'incident_type' => $data['incident_type'],
            'chronology' => $data['chronology'],
            'severity_grading' => $data['severity_grading'],
            'immediate_action' => $data['immediate_action_taken'],
            'reported_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        $this->passportService->appendEvent(
            patientId: $data['patient_id'],
            eventType: 'PATIENT_SAFETY_INCIDENT_FLAGGED',
            eventPayload: $payload,
            actorId: $data['reported_by_user_id']
        );

        return [
            'success' => true,
            'incident_id' => $incidentId,
            'payload' => $payload,
        ];
    }
}
