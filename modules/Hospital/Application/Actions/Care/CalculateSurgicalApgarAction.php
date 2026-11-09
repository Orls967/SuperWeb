<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Algorithms\SurgicalApgarScoreCalculator;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Services\PatientPassportService;

final class CalculateSurgicalApgarAction
{
    private SurgicalApgarScoreCalculator $calculator;
    private PatientPassportService $passportService;

    public function __construct(
        SurgicalApgarScoreCalculator $calculator,
        PatientPassportService $passportService
    ) {
        $this->calculator = $calculator;
        $this->passportService = $passportService;
    }

    /**
     * @param array<string, mixed> $surgicalData
     * @return array<string, mixed>
     */
    public function execute(int $admissionId, array $surgicalData, int $actorId): array
    {
        $admission = Admission::findOrFail($admissionId);
        $result = $this->calculator->evaluate($surgicalData);

        $admission->recordClinicalAssessment('surgical_apgar_score', $result);

        $this->passportService->appendEvent(
            patientId: $admission->patient_id,
            eventType: 'SURGICAL_APGAR_EVALUATED',
            eventPayload: [
                'admission_id' => $admission->id,
                'total_score' => $result['total_score'],
                'risk_tier' => $result['risk_tier'],
                'disposition' => $result['postop_disposition_recommendation'],
            ],
            actorId: $actorId
        );

        return [
            'success' => true,
            'admission_id' => $admission->id,
            'apgar_score' => $result['total_score'],
            'apgar_evaluation' => $result,
            'disposition' => $result['postop_disposition_recommendation'],
        ];
    }
}
