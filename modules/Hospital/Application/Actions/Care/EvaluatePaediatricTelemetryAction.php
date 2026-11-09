<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Algorithms\PaediatricEarlyWarningScoreCalculator;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Services\PatientPassportService;

final class EvaluatePaediatricTelemetryAction
{
    private PaediatricEarlyWarningScoreCalculator $calculator;
    private PatientPassportService $passportService;

    public function __construct(
        PaediatricEarlyWarningScoreCalculator $calculator,
        PatientPassportService $passportService
    ) {
        $this->calculator = $calculator;
        $this->passportService = $passportService;
    }

    /**
     * @param array<string, mixed> $vitals
     * @return array<string, mixed>
     */
    public function execute(int $admissionId, array $vitals, int $actorId): array
    {
        $admission = Admission::findOrFail($admissionId);
        $result = $this->calculator->evaluate($vitals);

        $admission->recordClinicalAssessment('paediatric_ews', $result);

        $this->passportService->appendEvent(
            patientId: $admission->patient_id,
            eventType: 'PAEDIATRIC_EWS_RECORDED',
            eventPayload: [
                'admission_id' => $admission->id,
                'total_score' => $result['total_pews_score'],
                'category' => $result['risk_category'],
                'action' => $result['escalation_action'],
            ],
            actorId: $actorId
        );

        return [
            'success' => true,
            'admission_id' => $admission->id,
            'pews_score' => $result['total_pews_score'],
            'pews_evaluation' => $result,
            'escalation' => $result['escalation_action'],
        ];
    }
}
