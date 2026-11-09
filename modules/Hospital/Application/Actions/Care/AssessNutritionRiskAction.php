<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Algorithms\ClinicalNutritionRiskCalculator;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Services\PatientPassportService;

final class AssessNutritionRiskAction
{
    private ClinicalNutritionRiskCalculator $calculator;
    private PatientPassportService $passportService;

    public function __construct(
        ClinicalNutritionRiskCalculator $calculator,
        PatientPassportService $passportService
    ) {
        $this->calculator = $calculator;
        $this->passportService = $passportService;
    }

    /**
     * @param array<string, mixed> $nutritionData
     * @return array<string, mixed>
     */
    public function execute(int $admissionId, array $nutritionData, int $actorId): array
    {
        $admission = Admission::findOrFail($admissionId);
        $result = $this->calculator->evaluate($nutritionData);

        $admission->recordClinicalAssessment('nutrition_risk_screening', $result);

        $this->passportService->appendEvent(
            patientId: $admission->patient_id,
            eventType: 'NUTRITION_RISK_ASSESSED',
            eventPayload: [
                'admission_id' => $admission->id,
                'total_nrs_score' => $result['total_nrs_score'],
                'is_at_risk' => $result['is_at_nutritional_risk'],
                'plan' => $result['clinical_action_plan'],
            ],
            actorId: $actorId
        );

        return [
            'success' => true,
            'admission_id' => $admission->id,
            'nutrition_risk_result' => $result,
            'action_plan' => $result['clinical_action_plan'],
        ];
    }
}
